<?php

class bdBank_XenForo_ControllerPublic_Attachment extends XFCP_bdBank_XenForo_ControllerPublic_Attachment
{
    public function actionIndex()
    {
        if (XenForo_Visitor::getUserId() > 0) {
            // our procedure only valid to registered user so... don't bother if guest is
            // viewing
            $userId = XenForo_Visitor::getUserId();
            $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
            $attachment = $this->_getAttachmentOrError($attachmentId);

            $tempHash = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);

            /** @var bdBank_XenForo_Model_Attachment $attachmentModel */
            $attachmentModel = $this->_getAttachmentModel();

            if ($attachmentModel->canViewAttachment($attachment, $tempHash) AND $attachment['user_id'] != $userId) {
                // process our stuff here
                $bank = bdBank_Model_Bank::getInstance();
                $extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
                $point = $bank->getActionBonus('attachment_downloaded', XenForo_Application::$time, $extension);
                if (bdBank_Helper_Number::comp($point, 0) === 1) {
                    // this attachment generates bonus for uploader
                    // check to make sure no duplicated bonus
                    if (!$attachmentModel->bdBank_isDownloaded($attachment, $userId)) {
                        $bank->personal()->give(
                            $attachment['user_id'],
                            $point,
                            $bank->comment('attachment_downloaded', $attachment['attachment_id'])
                        );

                        $attachmentModel->bdBank_markDownloaded($attachment, $userId);
                    }
                }
            }
        }

        return parent::actionIndex();
    }

    protected function _getAttachmentOrError($attachmentId)
    {
        static $cached = array();

        if (empty($cached[$attachmentId])) {
            $cached[$attachmentId] = parent::_getAttachmentOrError($attachmentId);
        }

        return $cached[$attachmentId];
    }
}
