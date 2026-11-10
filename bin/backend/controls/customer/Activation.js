/**
 * One-time customer activation request, submitted with the customer form.
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/Activation
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Activation', [
    'qui/controls/Control',
    'Users',
    'Locale',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Activation.css'
], function(QUIControl, Users, QUILocale) {
    'use strict';

    const lg = 'quiqqer/customer';

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/Activation',

        Binds: ['$onImport', '$onClick', '$render', '$onDestroy'],

        options: {
            userid: false,
            allowed: 0
        },

        initialize: function(options) {
            this.parent(options);
            this.$Input = null;
            this.$Wrapper = null;
            this.$Button = null;
            this.$Status = null;
            this.$User = null;

            this.addEvents({
                onImport: this.$onImport,
                onDestroy: this.$onDestroy
            });
        },

        $onImport: function() {
            this.$Input = this.getElm();
            this.$User = Users.get(this.getAttribute('userid'));
            this.$Input.type = 'hidden';

            this.$Wrapper = document.createElement('span');
            this.$Wrapper.className = 'quiqqer-customer-activation';

            this.$Button = document.createElement('button');
            this.$Button.type = 'button';
            this.$Button.className = 'qui-button';
            this.$Button.dataset.name = 'activation-button';
            this.$Button.addEventListener('click', this.$onClick);

            this.$Status = document.createElement('span');
            this.$Status.dataset.name = 'activation-status';
            this.$Status.id = this.getId() + '-status';
            this.$Status.setAttribute('role', 'status');
            this.$Status.setAttribute('aria-atomic', 'true');

            const description = this.$Input.getAttribute('aria-describedby');
            this.$Button.setAttribute(
                'aria-describedby',
                [this.$Status.id, description].filter(Boolean).join(' ')
            );

            this.$Wrapper.append(this.$Button, this.$Status);
            this.$Input.after(this.$Wrapper);
            this.$render();
        },

        getValue: function() {
            return this.$Input?.value || '0';
        },

        setValue: function(value) {
            this.$Input.value = value === true || value === 1 || value === '1' ? '1' : '0';
            this.$render();
            return this;
        },

        $onClick: function() {
            if (this.$Button.disabled) {
                return;
            }

            this.setValue(this.getValue() === '1' ? 0 : 1);
            this.$Input.dispatchEvent(new Event('input', {bubbles: true}));
            this.$Input.dispatchEvent(new Event('change', {bubbles: true}));
            this.fireEvent('change', [this, this.getValue()]);
        },

        $render: function() {
            const active = Number(this.$User.getAttribute('active')) === 1;
            const allowed = Number(this.getAttribute('allowed')) === 1;

            if (active || !allowed) {
                this.$Input.value = '0';
            }

            const pending = this.getValue() === '1';
            let status = 'inactive';
            let variant = 'badge-dark-light';

            if (active) {
                status = 'active';
                variant = 'badge-success-light';
            } else if (pending) {
                status = 'pending';
                variant = 'badge-warning-light';
            }

            this.$Button.disabled = active || !allowed;
            this.$Button.textContent = QUILocale.get(
                lg,
                'customer.user.properties.status.' + (pending ? 'cancel' : 'action')
            );
            this.$Status.className = 'badge badge-lg ' + variant;
            this.$Status.textContent = QUILocale.get(lg, 'customer.user.properties.status.' + status);
        },

        $onDestroy: function() {
            this.$Button?.removeEventListener('click', this.$onClick);
            this.$Wrapper?.remove();
        }
    });
});
