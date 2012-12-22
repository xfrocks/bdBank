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
		},
	};

	XenForo.register('form.Transfer', 'XenForo.bdBank_Transfer');
	XenForo.register('form.TransferConfirm', 'XenForo.bdBank_TransferConfirm');
}
(jQuery, this, document);