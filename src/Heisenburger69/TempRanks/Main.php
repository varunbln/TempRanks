<?php

declare(strict_types=1);

namespace Heisenburger69\TempRanks;

use DateTime;
use Exception;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class Main extends PluginBase
{

    /* @var Config */
    public $config;
<<<<<<< HEAD
    /**
     * @var SQLite3
     */
    public $db;
    /**
     * @var string
     */
    public $mode;
    /**
     * @var GroupManager
     */
    private $groupMgr;

    public function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->db = new SQLite3($this->getDataFolder() . "Ranks.db");
=======

    public function onEnable(): void
    {
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, ["Check Rank Expiry every 60 seconds" => true, "Time Left Message" => "You have {time_left} on your temporary {temprank} rank", "Rank Expired Message" => "Your {temprank} Rank has expired"]);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->db = new \SQLite3($this->getDataFolder() . "Ranks.db");
>>>>>>> parent of 2ad0e74... Begin Cleanup
        $this->db->exec("CREATE TABLE IF NOT EXISTS ranks (player TEXT PRIMARY KEY COLLATE NOCASE, oldrank TEXT, endtime TEXT);");
        if ($this->config->get("Check Rank Expiry every 60 seconds") === true) {
            $this->getScheduler()->scheduleRepeatingTask(new CheckTask($this), 20);
        }
<<<<<<< HEAD
        $this->mode = $this->getConfig()->get("Mode");
        if($this->mode !== "PurePerms" || $this->mode !== "Hierarchy") {
            $this->mode = "PurePerms";
            $this->getLogger()->emergency("TempRanks Mode incorrectly configured. Reverted to PurePerms");
        }
        if($this->mode === "Hierarchy" && $this->getServer()->getPluginManager()->getPlugin("Hierarchy") === null) {
            $this->mode = "PurePerms";
            $this->getLogger()->emergency("Hierarchy Plugin not found. Reverted to PurePerms");
=======
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $time = $this->getTimeLeft($playername);
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank = $pp->getUserDataMgr()->getGroup($pp->getPlayer($playername));
        if ($time !== null && $time !== "No temprank") {
            $msg = $this->config->get("Time Left Message");
            $msg = str_replace(array("{time_left}", "{temprank}"), array($time, $rank), $msg);
            $player->sendMessage($msg);
        }
        $exp = $this->getExpiryDate($playername);
        if ($exp === null) {
            return;
        }
        if (strtotime($exp) < time()) {
            $msg = $this->config->get("Rank Expired Message");
            $msg = str_replace("{temprank}", $rank, $msg);
            $player->sendMessage($msg);
            $this->removeRank($playername);
>>>>>>> parent of 2ad0e74... Begin Cleanup
        }
        $this->groupMgr = new GroupManager($this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "temprank":
                $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
                if ($sender->hasPermission("temprank.command")) {
                    if (isset($args[0])) {
                        if ($args[0] === "set") {
                            if (count($args) < 4) {
                                $sender->sendMessage(C::RED . "Do /temprank set <player> <rank> <duration>\n" . C::AQUA . "For Example: /temprank set Steve Admin 1d12h\nYear = y, Month = m, Day = d, Hour = h, Minute = i, Seconds = s");
                                return true;
                            }
                            $oldrank = $pp->getUserDataMgr()->getGroup($pp->getPlayer($args[1]));
                            $player = $this->getServer()->getPlayer($args[0]);
                            if ($player !== null) {
                                $playername = $player->getName();
                            } else {
                                $playername = (string)$args[1];
                            }
                            $group = $pp->getGroup($args[2]);
                            if ($group !== null) {
                                $time = $this->parseTimeFormat($args[3]);
                                $endtime = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")) + $time);
                                $stmt = $this->db->prepare("INSERT OR REPLACE INTO ranks (player, oldrank, endtime) VALUES (:player, :oldrank, :endtime);");
                                $stmt->bindValue(":player", $playername);
                                $stmt->bindValue(":oldrank", $oldrank);
                                $stmt->bindValue(":endtime", $endtime);
                                $stmt->execute();
                                $ppplayer = $pp->getPlayer($args[1]);
                                $pp->setGroup($ppplayer, $group);
                                $diff = strtotime($endtime) - time();
                                $length = $this->parseSecondToHuman($diff);
                                $sender->sendMessage(C::GREEN . "You set the $group Rank to $playername for $length");
                            }
                        } elseif ($args[0] === "remove") {
                            if (count($args) < 2) {
                                $sender->sendMessage(C::RED . "Use /temprank remove <player>");
                                return true;
                            }
                            $playername = $args[1];
                            $this->removeRank($playername);
                            $sender->sendMessage(C::GREEN . "Temp Rank successfully removed!");
                        } else {
                            $sender->sendMessage(C::RED . "Use /temprank set/remove");
                        }
                    } else {
                        $sender->sendMessage(C::RED . "Use /temprank set/remove");
                    }
                }
                break;
        }
        return true;
    }

    public function getTimeLeft($playername)
    {
        $date = date("Y-m-d H:i:s");
        $enddate = $this->getExpiryDate($playername);
        if ($enddate === null) {
            return "No temprank";
        }
        if (strtotime($enddate) < time()) {
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

    public function getExpiryDate($playername)
    {
        $stmt = $this->db->prepare("SELECT endtime FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playername);
        $result = $stmt->execute();
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr)) {
            return null;
        }
        return (string)$resultArr["endtime"];
    }

    //Thanks Thunder

    /**
     * @param string $duration
     * @return int|null
     */
    public function parseTimeFormat(string $duration): ?int
    {
        $parts = str_split($duration);
        $time_units = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second']; //Array of replacement
        $time = '';
        $i = -1;
        foreach ($parts as $part) {
            ++$i;
            if (!isset($time_units[$part])) {
                if (ctype_alpha($part)) return null; //Ensure only valid characters should pass
                continue;
            }
            $unit = $time_units[$part];
            $n = implode('', array_slice($parts, 0, $i));
            $time .= "$n $unit "; //Join number and unit
            array_splice($parts, 0, $i + 1);
            $i = -1;
        }
        $time = trim($time);
        $epoch = strtotime($time, 0);
        if ($epoch === false) return null;
        return $epoch;
    }

    /**
     * @param $seconds
     * @return string|null
     * @throws Exception
     */
    public function parseSecondToHuman($seconds): ?string
    {
        $dt1 = new DateTime("@0");
        $dt2 = new DateTime("@$seconds");
        $diff = $dt1->diff($dt2);
        if ($diff === false) return null;
        $str = [];
        if ($diff->y > 0) $str[] = $diff->y . ' year(s)';
        if ($diff->m > 0) $str[] = $diff->m . ' month(s)';
        if ($diff->d > 0) $str[] = $diff->d . ' day(s)';
        if ($diff->h > 0) $str[] = $diff->h . ' hour(s)';
        if ($diff->i > 0) $str[] = $diff->i . ' minute(s)';
        if ($diff->s > 0) $str[] = $diff->s . ' second(s)';
        if (count($str) > 0) {
            $str = implode(', ', $str);
        } else {
            $str = $diff->s . ' second';
        }
        return $str;
    }

}
