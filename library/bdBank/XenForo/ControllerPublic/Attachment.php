<?php

class bdBank_XenForo_ControllerPublic_Attachment extends XFCP_bdBank_XenForo_ControllerPublic_Attachment
{
    public function actionIndex()
    {
        if (XenForo_Visitor::getUserId() > 0) {
            // our procedure only valids to registered user so... don't bother if guest is
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
                $point = $bank->getActionBonus('attachment_downloaded', $extension);
                if (bdBank_Helper_Number::comp($point, 0) === 1) {
                    // this attachment generates bonus for uploader
                    // check to make sure no duplicated bonus
                    if (!$attachmentModel->bdBank_isDownloaded($attachment, $userId)) {
                        // IMPORTANT: now we will have to see if the user specified a price for this or
                        // not
                        // if a price is set, we will ask the downloader to pay
                        // otherwise, give the normal bonus
                        if ($attachment['bdbank_price'] > 0) {
                            // price is set
                            $hash = md5($userId . $attachment['attachment_id'] . $attachment['attach_date']);
                            // check if the downloader agreed to pay
                            if ($this->isConfirmedPost() AND $this->_input->filterSingle('hash', XenForo_Input::STRING) == $hash) {
                                // agreed
                                try {
                                    $bank->personal()->transfer($userId, $attachment['user_id'], $attachment['bdbank_price'], $bank->comment('attachment_downloaded_paid', $attachment['attachment_id']), bdBank_Model_Bank::TYPE_SYSTEM);
                                } catch (bdBank_Exception $be) {
                                    return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_generic', array('error' => $be->getMessage())));
                                }
                            } else {
                                // not agreed yet, display the form
                                $viewParams = array(
                                    'attachment' => $attachment,
                                    'hash' => $hash,
                                );
                                return $this->responseView('bdBank_ViewPublic_Attachment_ConfirmToPay', 'bdbank_page_attachment_confirm_to_pay', $viewParams);
                            }
                        } else {
                            // no custom price
                            $bank->personal()->give($attachment['user_id'], $point, $bank->comment('attachment_downloaded', $attachment['attachment_id']));
                        }

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
