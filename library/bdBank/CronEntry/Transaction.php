<?php

class bdBank_CronEntry_Transaction
{
    public static function archive()
    {
        XenForo_Application::defer('bdBank_Deferred_Archive', array());
    }
}
