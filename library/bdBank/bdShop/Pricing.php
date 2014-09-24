<?php

class bdBank_bdShop_Pricing extends bdShop_Pricing_Abstract
{

	protected function _getConfiguration()
	{
		$name = new XenForo_Phrase('bdbank_money');
		$name .= '';
		// force to string

		return array('name' => $name, );
	}

}
