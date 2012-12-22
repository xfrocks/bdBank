/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	XenForo.bdBank_Transfer = function($form) { this.__construct($form); };
	XenForo.bdBank_Transfer.prototype = {
		__construct: function($form) {
			this.$form = $form;
			
			this.$form.bind({
				AutoValidationComplete: $.context(this, 'AutoValidationComplete')
			});
		},
		
		AutoValidationComplete: function(e) {
			e.preventDefault();
			
			new XenForo.ExtLoader(e.ajaxData, $.context(this, 'createOverlay'));
		},

		createOverlay: function($overlay) {
			var contents = ($overlay && $overlay.templateHtml) ? $overlay.templateHtml : $overlay;
			this.overlay = XenForo.createOverlay(null, contents, {});

			this.overlay.load();
		}
	};
	
	XenForo.bdBank_TransferConfirm = function($form) { this.__construct($form); };
	XenForo.bdBank_TransferConfirm.prototype = {
		__construct: function($form) {
			this.$form = $form;
			
			this.$form.bind({
				AutoValidationComplete: $.context(this, 'AutoValidationComplete')
			});
		},
		
		AutoValidationComplete: function(e) {
			var rtn = this.$form.find('input[name=rtn]').val();
			if (rtn && rtn == window.location.href.replace(window.location.hash, '')) {
				// we are on the same page so do not redirect
				// the check ignores the hash
				this.$form.data('redirect', 0);
			}
			
			if (e.ajaxData.bdBank_users) {
				eUpdate = $.Event('bdBank_BalanceUpdate'),
				eUpdate.users = e.ajaxData.bdBank_users;
				eUpdate.$source = this.$form;
				$(document).trigger(eUpdate);
			}
		}
	};
	
	XenForo.bdBank_Balance = function($element) { this.__construct($element); };
	XenForo.bdBank_Balance.prototype = {
		__construct: function($element) {
			this.$element = $element;
			this.userId = $element.data('userid');
			
			var $document = $(document);
			
			// try to upload other balance in the page
			// useful when a new post is updated via AJAX
			eUpdate = $.Event('bdBank_BalanceUpdate'),
			eUpdate.users = {};
			eUpdate.users[this.userId] = {
				user_id: this.userId,
				balance: $element.data('balance'),
				balance_formatted: $element.html()
			};
			eUpdate.$source = $element;
			$document.trigger(eUpdate);
			
			$document.bind({
				bdBank_BalanceUpdate: $.context(this, 'update')
			});
		},
		
		update: function(e) {
			for (var i in e.users) {
				if (e.users[i].user_id == this.userId) {
					this.$element.html(e.users[i].balance_formatted);
				}
			}
		}
	};

	XenForo.register('form.Transfer', 'XenForo.bdBank_Transfer');
	XenForo.register('form.TransferConfirm', 'XenForo.bdBank_TransferConfirm');
	XenForo.register('.bdBank_Balance', 'XenForo.bdBank_Balance');
}
(jQuery, this, document);