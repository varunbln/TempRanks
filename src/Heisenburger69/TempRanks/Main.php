<?php

declare(strict_types=1);

namespace Heisenburger69\TempRanks;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class Main extends PluginBase implements Listener {

    /* @var Config*/
    public $config;

    public function onLoad(){
        @mkdir($this->getDataFolder());
    }

    public function onEnable() : void{
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, ["Check Rank Expiry every 60 seconds" => true, "Time Left Message" => "You have {time_left} on your temporary {temprank} rank", "Rank Expired Message" => "Your {temprank} Rank has expired"]);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->db = new \SQLite3($this->getDataFolder() . "Ranks.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS ranks (player TEXT PRIMARY KEY COLLATE NOCASE, oldrank TEXT, endtime TEXT);");
        if($this->config->get("Check Rank Expiry every 60 seconds") === true){
            $this->getScheduler()->scheduleRepeatingTask(new CheckTask($this), 20);
        }
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $time = $this->getTimeLeft($playername);
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank =  $pp->getUserDataMgr()->getGroup($pp->getPlayer($playername), );
        if($time !== null && $time !== "No temprank") {
            $msg = $this->config->get("Time Left Message");
            $msg = str_replace(array("{time_left}", "{temprank}"), array($time, $rank), $msg);
            $player->sendMessage($msg);
        }
        $exp = $this->getExpiryDate($playername);
        if($exp === null) {
            return;
        }
        if(strtotime($exp) < time()) {
            $msg = $this->config->get("Rank Expired Message");
            $msg = str_replace("{temprank}", $rank, $msg);
            $player->sendMessage($msg);
            $this->removeRank($playername);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch($command->getName()){
            case "temprank":
                $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
                if($sender->hasPermission("temprank.command")) {
                    if(isset($args[0])) {
                        if($args[0] === "set") {
                            if(isset($args[1])) {
                                $oldrank =  $pp->getUserDataMgr()->getGroup($pp->getPlayer($args[1]), );
                                $player = $this->getServer()->getPlayer($args[0]);
                                if($player !== null) {
                                    $playername = $player->getName();
                                } else {
                                    $playername = (string)$args[1];
                                }
                                if(isset($args[2])) {
                                    $group = $pp->getGroup($args[2]);
                                    if($group !== null) {
                                        if(isset($args[3])) {
                                            $num = (int)$args[3];
                                            if(is_int($num) && $num > 0) {
                                                $length = $num;
                                                if(isset($args[4])) {
                                                    switch ($args[4]) {
                                                        case "minutes":
                                                            $time = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")." +$length minutes"));
                                                            $interval = "minutes";
                                                            break;
                                                        case "hours":
                                                            $time = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")." +$length hours"));
                                                            $interval = "hours";
                                                            break;
                                                        case "days":
                                                            $time = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")." +$length days"));
                                                            $interval = "days";
                                                            break;
                                                    }
                                                    $stmt = $this->db->prepare("INSERT OR REPLACE INTO ranks (player, oldrank, endtime) VALUES (:player, :oldrank, :endtime);");
                                                    $stmt->bindValue(":player", $playername);
                                                    $stmt->bindValue(":oldrank", $oldrank);
                                                    $stmt->bindValue(":endtime", $time);
                                                    $stmt->execute();
                                                    $ppplayer = $pp->getPlayer($args[1]);
                                                    $pp->setGroup($ppplayer, $group);
                                                    $sender->sendMessage(C::GREEN . "You set the $group Rank to $playername for $length $interval");
                                                } else {
                                                    $sender->sendMessage(C::RED . "Use /temprank set <player> <group> <duration> <minutes/hours/days>" . "\n" . C::AQUA . "For Example: /temprank set Steve Admin 5 minutes");
                                                }
                                            } else {
                                                $sender->sendMessage(C::RED . "$args[3] is not a positive integer");
                                            }
                                        } else {
                                            $sender->sendMessage(C::RED . "Use /temprank set <player> <group> <duration> <minutes/hours/days>" . "\n" . C::AQUA . "For Example: /temprank set Steve Admin 5 minutes");
                                        }
                                    } else {
                                        $sender->sendMessage(C::RED . "Group $args[2] not found!");
                                    }
                                } else {
                                    $sender->sendMessage(C::RED . "Use /temprank set <player> <group> <duration> <minutes/hours/days>" . "\n" . C::AQUA . "For Example: /temprank set Steve Admin 5 minutes");
                                }
                            } else {
                                $sender->sendMessage(C::RED . "Use /temprank set <player> <group> <duration> <minutes/hours/days>" . "\n" . C::AQUA . "For Example: /temprank set Steve Admin 5 minutes");
                            }
                        } elseif($args[0] === "remove") {
                            if(isset($args[1])) {
                                $playername = $args[1];
                                $this->removeRank($playername);
                                $sender->sendMessage(C::GREEN . "Temp Rank successfully removed!");
                            } else {
                                $sender->sendMessage(C::RED . "Use /temprank remove <player>");
                            }
                        } else {
                            $sender->sendMessage(C::RED . "Use /temprank set/remove");
                        }
                    } else {
                        $sender->sendMessage(C::RED . "Use /temprank set/remove");
                    }
                    return true;
                }
                return true;
                break;
        }
    }

    public function getTimeLeft($playername) {
        $date = date("Y-m-d H:i:s");
        $enddate = $this->getExpiryDate($playername);
        if($enddate === null) {
            return "No temprank";
        }
        if(strtotime($enddate) < time()) {
            return null;
        }
        $datetime1 = date_create($date);
        $datetime2 = date_create($enddate);
        $interval = date_diff($datetime1, $datetime2);
        $min = $interval->format('%i');
        $sec = $interval->format('%s');
        $hour = $interval->format('%h');
        $mon = $interval->format('%m');
        $day = $interval->format('%d');
        $year = $interval->format('%y');
        if ($interval->format('%i%h%d%m%y') == "00000") {
            return $sec . " Seconds";

        } else if ($interval->format('%h%d%m%y') == "0000") {
            return $min . " Minutes";
        } else if ($interval->format('%d%m%y') == "000") {
            return $hour . " Hours";
        } else if ($interval->format('%m%y') == "00") {
            return $day . " Days";
        } else if ($interval->format('%y') == "0") {
            return $mon . " Months";
        } else {
            return $year . " Years";
        }
    }

    public function getExpiryDate($playername){
        $stmt = $this->db->prepare("SELECT endtime FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playername);
        $result = $stmt->execute();
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if(empty($resultArr)) {
            return null;
        }
        return (string)$resultArr["endtime"];
    }

    public function getOldGroup($playername){
        $stmt = $this->db->prepare("SELECT oldrank FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playername);
        $result = $stmt->execute();
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if(empty($resultArr)) {
            return null;
        }
        return (string)$resultArr["oldrank"];
    }

    public function removeRank($playername) {
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $ppplayer = $pp->getPlayer($playername);
        $group = $this->getOldGroup($playername);
        if($group === null) {
            $ppgroup = $pp->getDefaultGroup();
            $pp->setGroup($ppplayer, $ppgroup);
            return;
        }
        $ppgroup = $pp->getGroup($group);
        $pp->setGroup($ppplayer, $ppgroup);
        $stmt = $this->db->prepare("DELETE FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playername);
        $stmt->execute();
    }

}
