<?php

namespace QUI\FrontendUsers\Cleanup;

use League\CLImate\CLImate;
use QUI;

/**
 * Console utils for Namefruits
 *
 * Copies a project from LIVE to DEV
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Console extends QUI\System\Console\Tool
{
    /**
     * DB object for remote Juicer DB
     *
     * @var QUI\Database\DB
     */
    protected $ServerDB;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->setName('frontend-users:cleanup')
            ->setDescription(
                'Cleanup tool for users -> Delete user accounts that meet certain criteria'
            );

        $this->addArgument(
            'createDateFrom',
            'Delete users created as of and including the specified date. [YYYY-MM-DD]',
            false,
            true
        );

        $this->addArgument(
            'createDateTo',
            'Delete users created up to and including the specified date. [YYYY-MM-DD]',
            false,
            true
        );

        $this->addArgument(
            'atLeastDaysOld',
            'Delete users older than X days. [X = positive integer]',
            false,
            true
        );

        $this->addArgument(
            'atLeastAtLeastNotLoggedInForDays',
            'Delete users whose last login was X or more days ago. [X = positive integer]',
            false,
            true
        );

        $this->addArgument(
            'activeStatus',
            'Delete users whose active status equals X. [X = -1,0,1]',
            false,
            true
        );

        $this->addArgument(
            'inGroups',
            'Delete users who are in the given groups (comma-separated group IDs)',
            false,
            true
        );

        $this->addArgument(
            'notInGroups',
            'Delete users who are NOT in the given groups (comma-separated group IDs)',
            false,
            true
        );

        $this->addArgument(
            'delete',
            'Actually delete the users that are selected via the given filters',
            false,
            true
        );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        QUI\Permissions\Permission::isAdmin();

        if (!defined('ADMIN')) {
            define('ADMIN', 1);
        }

        if (!defined('SYSTEM_INTERN')) {
            define('SYSTEM_INTERN', 1);
        }


        $sql   = "SELECT `id`, `username` FROM ".QUI::getDBTableName('users');
        $where = [];

        // createDateFrom
        $createDateFrom = $this->getCreateDateFrom();

        if (!empty($createDateFrom)) {
            $where[] = '`regdate` >= '.$createDateFrom;
        }

        // createDateTo
        $createDateTo = $this->getCreateDateTo();

        if (!empty($createDateTo)) {
            $where[] = '`regdate` <= '.$createDateTo;
        }

        // atLeastDaysOld
        $atLeastDaysOld = $this->getAtLeastDaysOld();

        if (!empty($atLeastDaysOld)) {
            $where[] = '`regdate` <='.$atLeastDaysOld;
        }

        // atLeastAtLeastNotLoggedInForDays
        $atLeastAtLeastNotLoggedInForDays = $this->getAtLeastNotLoggedInForDays();

        if (!empty($atLeastAtLeastNotLoggedInForDays)) {
            $where[] = '`lastvisit` <='.$atLeastAtLeastNotLoggedInForDays;
        }

        // activeStatus
        $activeStatus = $this->getActiveStatus();

        if ($activeStatus !== false) {
            $where[] = '`active` = '.$activeStatus;
        }

        // inGroups
        $inGroups = $this->getInGroups();

        if (!empty($inGroups)) {
            $whereOr = [];

            foreach ($inGroups as $groupId) {
                $whereOr[] = '`usergroup` LIKE "%,'.$groupId.',%"';
            }

            $where[] = '('.implode(" OR ", $whereOr).')';
        }

        // notInGroups
        $notInGroups = $this->getNotInGroups();

        if (!empty($notInGroups)) {
            $whereOr = [];

            foreach ($notInGroups as $groupId) {
                $whereOr[] = '`usergroup` NOT LIKE "%,'.$groupId.',%"';
            }

            $where[] = '('.implode(" OR ", $whereOr).')';
        }

        // custom attributes
        foreach ($this->params as $k => $v) {
            $k = str_replace('--', '', $k);

            // $v is boolean TRUE if the user did not specify a value via CLI but only the attribute name
            if ($this->inConsole() && $v === true) {
                continue;
            }

            if (mb_strpos($k, 'attr-') === 0) {
                $attribute = mb_substr($k, 5);

                if (is_bool($v)) {
                    $v = $v ? 'true' : 'false';
                }

                $where[] = '`extra` LIKE "%\"'.$attribute.'\":'.$v.'%" OR `extra` LIKE "%\"'.$attribute.'\":\"'.$v.'\"%"';
            }
        }

        if (empty($where)) {
            $this->exitFail('No filter criteria for users given. Please specify at least one filter criterion');
            return;
        }

        // SuperUsers can never be deleted by this script
        $where[] = '`su` != 1';

        // Only delete users that have registered via frontend
        $where[] = '`extra` NOT LIKE "%\"quiqqer.frontendUsers.registrar\"%"';

        $sql .= ' WHERE '.implode(' AND ', $where);

        try {
            $Stmt = QUI::getPDO()->prepare($sql);
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            $this->exitFail($Exception->getMessage());
            return;
        }

        $this->writeLn("\n");

        if (empty($result)) {
            $this->writeLn("No users were found that match the given criteria.");

            $this->writeLn("\n");
            $this->exitSuccess();
            return;
        }

        $delete = $this->getArgument('delete') === true;

        $this->writeLn("Number of users to delete: ".count($result)."\n");

        if ($this->inConsole()) {
            $Climate = new CLImate();
            $Climate->table($result);

            if (empty($delete)) {
                $this->writeLn("Should the selected users be deleted from the QUIQQER system? [y/N]: ");
                $deleteInput = $this->readInput();

                if (mb_strtolower($deleteInput) === 'y') {
                    $delete = true;
                }
            }
        }

        if ($delete === true) {
            $Users          = QUI::getUsers();
            $deletedCounter = 0;

            $this->writeLn("Deleting users...\n");

            foreach ($result as $row) {
                $uid = $row['id'];
                $this->writeLn("Delete user #".$uid."...");

                try {
                    $User = $Users->get($uid);
                    $User->delete();

                    $deletedCounter++;
                    $this->write(" OK!");
                } catch (\Exception $Exception) {
                    $this->write(" ERROR: ".$Exception->getMessage());
                }
            }

            $this->writeLn("\nDeleted users: ".$deletedCounter."\n");
        }

        $this->writeLn("\n");
        $this->exitSuccess();
    }

    /**
     * Get createDateFrom filter
     *
     * @return false|int - False if not configured; timestamp otherwise
     */
    protected function getCreateDateFrom()
    {
        $date = $this->getArgument('createDateFrom');

        if (empty($date)) {
            return false;
        }

        $Date = new \DateTime($date);
        return $Date->getTimestamp();
    }

    /**
     * Get createDateTo filter
     *
     * @return false|int - False if not configured; timestamp otherwise
     */
    protected function getCreateDateTo()
    {
        $date = $this->getArgument('createDateTo');

        if (empty($date)) {
            return false;
        }

        $Date = new \DateTime($date);
        return $Date->getTimestamp();
    }

    /**
     * Get atLeastDaysOld filter
     *
     * @return false|int - False if not configured; timestamp (max account age) otherwise
     */
    protected function getAtLeastDaysOld()
    {
        $days = $this->getArgument('atLeastDaysOld');

        if (empty($days) || !is_numeric($days)) {
            return false;
        }

        return strtotime('-'.(int)$days.' days');
    }

    /**
     * Get atLeastAtLeastNotLoggedInForDays filter
     *
     * @return false|int - False if not configured; timestamp (max login date) otherwise
     */
    protected function getAtLeastNotLoggedInForDays()
    {
        $days = $this->getArgument('atLeastAtLeastNotLoggedInForDays');

        if (empty($days) || !is_numeric($days)) {
            return false;
        }

        return strtotime('-'.(int)$days.' days');
    }

    /**
     * Get activeStatus filter
     *
     * @return false|int - False if not configured; allowed active status otherwise
     */
    protected function getActiveStatus()
    {
        $activeStatus = $this->getArgument('activeStatus');

        if ($activeStatus === false) {
            return false;
        }

        $activeStatus = (int)$activeStatus;

        switch ($activeStatus) {
            case -1:
            case 0:
            case 1:
                return $activeStatus;
        }

        return false;
    }

    /**
     * Get inGroups filter
     *
     * @return false|int[] - False if not configured; int[] with group IDs otherwise
     */
    protected function getInGroups()
    {
        $groupIds = $this->getArgument('inGroups');

        if (empty($groupIds)) {
            return false;
        }

        $groupIds = explode(',', $groupIds);

        array_walk($groupIds, function (&$v) {
            $v = (int)$v;
        });

        return $groupIds;
    }

    /**
     * Get notInGroups filter
     *
     * @return false|int[] - False if not configured; int[] with group IDs otherwise
     */
    protected function getNotInGroups()
    {
        $groupIds = $this->getArgument('notInGroups');

        if (empty($groupIds)) {
            return false;
        }

        $groupIds = explode(',', $groupIds);

        array_walk($groupIds, function (&$v) {
            $v = (int)$v;
        });

        return $groupIds;
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess()
    {
        $this->writeLn('Konsolen-Tool Ausführung erfolgreich abgeschlossen.');
        $this->writeLn("");

        if ($this->inConsole()) {
            exit(0);
        }
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
     * @return void
     */
    protected function exitFail($msg)
    {
        $this->writeLn('Skript-Abbruch wegen Fehler:');
        $this->writeLn("");
        $this->writeLn($msg);
        $this->writeLn("");
        $this->writeLn("");

        if ($this->inConsole()) {
            exit(1);
        }
    }

    /**
     * Check if environment is CONSOLEs
     *
     * @return bool
     */
    protected function inConsole()
    {
        return defined('QUIQQER_CONSOLE') && QUIQQER_CONSOLE;
    }
}
