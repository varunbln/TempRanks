<?php

namespace Heisenburger69\TempRanks;

class GroupManager
{
    /**
     * @var Main
     */
    private $plugin;

    /**
     * GroupManager constructor.
     * @param Main $plugin
     */
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param string $playerName
     */
    public function removeRank(string $playerName)
    {
        if($this->plugin->mode === "PurePerms") {
            $this->removeRankPurePerms($playerName);
        }
        if($this->plugin->mode === "Hierarchy") {
            $this->removeRankHierarchy($playerName);
        }
    }

    /**
     * @param string $playerName
     * @return string|null
     */
    public function getOldGroup(string $playerName)
    {
        $stmt = $this->plugin->db->prepare("SELECT oldrank FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playerName);
        $result = $stmt->execute();
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if (empty($resultArr)) {
            return null;
        }
        return (string)$resultArr["oldrank"];
    }

    private function removeRankPurePerms(string $playerName)
    {
        $pp = $this->plugin->getServer()->getPluginManager()->getPlugin("PurePerms");
        $ppPlayer = $pp->getPlayer($playerName);
        $group = $this->getOldGroup($playerName);
        if ($group === null) {
            $ppgroup = $pp->getDefaultGroup();
            $pp->setGroup($ppPlayer, $ppgroup);
            return;
        }
        $ppGroup = $pp->getGroup($group);
        $pp->setGroup($ppPlayer, $ppGroup);
        $stmt = $this->plugin->db->prepare("DELETE FROM ranks WHERE player = :player;");
        $stmt->bindValue(":player", $playerName);
        $stmt->execute();
    }

    private function removeRankHierarchy(string $playerName)
    {
        $hierarchy = $this->plugin->getServer()->getPluginManager()->getPlugin("Hierarchy");
        $player = $this->plugin->getServer()->getPlayer($playerName);
        if($player === null) {
            return;
        }
        $hierarchyMember = $hierarchy->getMemberFactory()->getMember($player);
        $hierarchyMember->removeRole();
    }

}