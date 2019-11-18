<?php

declare(strict_types=1);

namespace Heisenburger69\TempRanks;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    /* @var Config*/
    public $config;

    public function onLoad(){
        @mkdir($this->getDataFolder());
    }

	public function onEnable() : void{
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, ["Check Rank on Join" => "true", "Check Rank during Session" => "false"]);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

	}

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch($command->getName()){
            case "temprank":
                $this->getServer()->getPluginManager()->registerEvents($this, $this);
                $this->db = new \SQLite3($this->getDataFolder() . "Ranks.db");
                $this->db->exec("CREATE TABLE IF NOT EXISTS ranks (player TEXT PRIMARY KEY COLLATE NOCASE, temprank TEXT, oldrank TEXT, time TEXT);");
                break;
        }
    }

	public function onDisable() : void{

	}
}
