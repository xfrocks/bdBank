<?php
class bdBank_AntiCheating {
	protected static $_stripParser = null;
	
	public static function checkPostQuality(XenForo_DataWriter_DiscussionMessage_Post $dw) {
		/*
		// uncommented because attachment has its own bonus already
		if ($dw->get('attach_count') > 0) {
			// a post with attachment should be good
			return true;
		}
		*/
		
		if ($dw->get('message_state') != 'visible') {
			// soft-deleted post, nothing to do here
			return false;
		}
		
		// check for the message
		$threshold = bdBank_Model_Bank::options('post_chars_threshold');
		if ($threshold > 0) {
			$parser = self::getStripParser();
			$message = $parser->render($dw->get('message'));
			if (bdBank_Model_Bank::helperStrLen($message) > $threshold) {
				// longer than the threshold, good
				return true;
			}
		} else {
			// no threshold, everything will pass
			return true;
		}
		
		return false; // not qualified
	}
	
	public static function getStripParser() {
		if (self::$_stripParser === null) {
			$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Strip', false);
			$formatter->setMaxQuoteDepth(0);
			$formatter->stripAllBbCode(true);
			$formatter->setCensoring(true);
			
			self::$_stripParser = new XenForo_BbCode_Parser($formatter);;
		}
		
		return self::$_stripParser;
	}
}