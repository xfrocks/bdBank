<?php

class bdBank_Exception extends Zend_Exception {
	const NOTHING_TO_DO = 'nothing_to_do';
	const USER_NOT_FOUND = 'user_not_found';
	const NOT_ENOUGH_MONEY = 'not_enough_money';
}