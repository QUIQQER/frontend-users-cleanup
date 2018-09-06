<?php

namespace QUI\FrontendUsers\Cleanup;

use QUI;
use QUI\FrontendUsers\Handler as FrontendUsers;

class Cron
{
    /**
     * Cron that cleans up
     *
     * @param $params
     */
    public static function cleanup($params)
    {
        $ConsoleTool = new Console();

        foreach ($params as $k => $v) {
            switch ($k) {
                case 'emailVerified':
                    $ConsoleTool->setArgument('attr-' . FrontendUsers::USER_ATTR_EMAIL_VERIFIED, boolval($v));
                    break;

                default:
                    $ConsoleTool->setArgument($k, $v);
            }
        }

        $ConsoleTool->setArgument('delete', true);
        $ConsoleTool->execute();
    }
}
