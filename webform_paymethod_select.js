(function ($) {

var ExecuteOnceReady = function(exec) {
  this.execute_function = exec;
  this.executed = false;
  this.started = false;
  this.needed = 0;
  return this;
}
ExecuteOnceReady.prototype.execute = function() {
  if (!this.executed) {
    this.executed = true;
    this.execute_function();
  }
}
ExecuteOnceReady.prototype.start = function() {
  var self = this;
  this.started = true;
  if (this.needed <= 0) {
    // Force this to be asynchronous.
    setTimeout(function() { self.execute(); }, 0);
  }
}
ExecuteOnceReady.prototype.ready = function() {
  this.needed--;
  if (this.started && this.needed <= 0) {
    this.execute();
  }
}
ExecuteOnceReady.prototype.need = function() {
  this.needed++;
}


var Webform = function($form) {
  this.$form = $form;
  this.activeButton = null;
  this.passSubmit = false;
  this.bind();
  return this;
};

Webform.prototype.bind = function() {
  var self = this;
  this.$form.find('.form-actions input[type=submit]').click(function(event) {
    if (!self.passSubmit) {
      self.activeButton = event.target;
    }
  });
  this.$form.bind('submit', function (event) {
    var button = self.activeButton;
    if (button && $(button).attr('formnovalidate') || self.passSubmit) {
      return;
    }
    event.preventDefault();
    self.validate(new ExecuteOnceReady(self.submitFunction()));
    self.activeButton = null;
  });
  if (this.$form.find('[name=webform_ajax_wrapper_id]').length > 0) {
    this.$form.bind('form-pre-serialize', function(event, $form, options, veto) {
      var ed = options.data;
      var button = $form.find('input[name="'+ed._triggering_element_name+'"][value="'+ed._triggering_element_value+'"]').first();
      if (button && $(button).attr('formnovalidate') || self.passSubmit) {
        return;
      }
      veto.veto = true;
      self.validate(new ExecuteOnceReady(self.ajaxSubmitFunction(options)));
      self.activeButton = null;
    });
  }
};

Webform.prototype.validate = function(submitter) {
  if (Drupal.payment_handler) {
    this.$form.find('.paymethod-select-wrapper').each(function() {
      var pmid, $fieldset;
      var $radios = $('.paymethod-select-radios input:checked', this);
      if ($radios.length > 0) {
        pmid = $('.paymethod-select-radios input:checked', this).val();
        $fieldset = $('[data-pmid='+pmid+']', this);
      }
      else {
        $fieldset = $('.payment-method-form', this).first();
        pmid = parseInt($fieldset.data('pmid'));
      }
      if (pmid in Drupal.payment_handler) {
        var ret = Drupal.payment_handler[pmid](pmid, $fieldset, submitter);
        if (!ret) {
          submitter.need();
        }
      }
    });
  }
  submitter.start();
}

Webform.prototype.submitFunction = function() {
  var self = this;
  var button = this.activeButton;
  return function() {
    self.passSubmit = true;
    if (button) {
      $(button).click();
    }
    else {
      self.$form.submit();
    }
    self.passSubmit = false;
  };
};

Webform.prototype.ajaxSubmitFunction = function(options) {
  var self = this;
  return function() {
    self.passSubmit = true;
    self.$form.ajaxSubmit(options);
    self.passSubmit = false;
  }
}

Drupal.behaviors.webform_paymethod_select = {
  attach: function(context) {
    var self = this;
    $('.payment-method-form', context).closest('form').each(function() {
      var $form = $(this);
      new Webform($form);
    });
  },
};
})(jQuery);
