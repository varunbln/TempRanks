<?php

namespace Heisenburger69\TempRanks;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

class EventListener implements Listener
{
    /**
     * @var Main
     */
    private $plugin;

    /**
     * EventListener constructor.
     * @param Main $plugin
     */
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $time = $this->plugin->getTimeLeft($playername);
        $pp = $this->plugin->getServer()->getPluginManager()->getPlugin("PurePerms");
        $rank = $pp->getUserDataMgr()->getGroup($pp->getPlayer($playername));
        if ($time !== null && $time !== "No temprank") {
            $msg = $this->plugin->getConfig()->get("Time Left Message");
            $msg = str_replace(array("{time_left}", "{temprank}"), array($time, $rank), $msg);
            $player->sendMessage($msg);
        }
        $exp = $this->plugin->getExpiryDate($playername);
        if ($exp === null) {
            return;
        }
        if (strtotime($exp) < time()) {
            $msg = $this->plugin->getConfig()->get("Rank Expired Message");
            $msg = str_replace("{temprank}", $rank, $msg);
            $player->sendMessage($msg);
            $this->plugin->removeRank($playername);
        }
    }

}