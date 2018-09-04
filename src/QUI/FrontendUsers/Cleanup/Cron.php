<?php

namespace QUI\FrontendUsers\Cleanup;

use QUI;

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
            $ConsoleTool->setArgument($k, $v);
        }

        $ConsoleTool->setArgument('delete', true);
        $ConsoleTool->execute();
    }
}
