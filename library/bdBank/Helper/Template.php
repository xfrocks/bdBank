<?php

class bdBank_Helper_Template
{
	public static function number($operation, $a, $b)
	{
		return call_user_func(array('bdBank_Helper_Number', $operation), $a, $b);
	}
}
