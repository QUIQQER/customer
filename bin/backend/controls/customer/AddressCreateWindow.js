/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/AddressCreateWindow
 * @author www.csg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/AddressCreateWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/AddressEdit.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/AddressCreateWindow.css'

], function(QUI, QUIConfirm, QUIAjax, QUILocale, Mustache, template) {
    'use strict';

    const lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/AddressCreateWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            userId: false, // id of the user
            maxHeight: 700,
            maxWidth: 600,
            autoclose: false,
            icon: 'fa fa-share'
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get(lg, 'address.create.title')
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function() {
            const self = this;

            this.Loader.show();
            this.getContent().addClass('quiqqer-customer-window-create-address');

            this.getContent().set('html', Mustache.render(template, {
                titleAddress: QUILocale.get('quiqqer/core', 'address'),
                textAddressCompany: QUILocale.get('quiqqer/core', 'company'),
                textAddressSalutation: QUILocale.get('quiqqer/core', 'salutation'),
                textAddressFirstname: QUILocale.get('quiqqer/core', 'firstname'),
                textAddressLastname: QUILocale.get('quiqqer/core', 'lastname'),
                textAddressStreet: QUILocale.get('quiqqer/core', 'street'),
                textAddressZIP: QUILocale.get('quiqqer/core', 'zip'),
                textAddressCity: QUILocale.get('quiqqer/core', 'city'),
                textAddressCountry: QUILocale.get('quiqqer/core', 'country'),
                textAddressSuffix: QUILocale.get('quiqqer/core', 'address.suffix'),

                textAddressTelFaxMobile: QUILocale.get(lg, 'address.telFaxMobile'),
                textAddressEmail: QUILocale.get(lg, 'address.email'),
                textAddressGeneral: QUILocale.get(lg, 'address.general')
            }));

            this.getElm().getElement('[name="add-phone"]').addEvent('click', function(e) {
                e.stop();
                self.addPhone();
            });

            this.getElm().getElement('[name="add-email"]').addEvent('click', function(e) {
                e.stop();
                self.addEmail();
            });

            this.Loader.hide();
        },

        /**
         * submit
         */
        submit: function() {
            const self = this;

            this.Loader.show();

            const Form = self.getElm().getElement('form'),
                elements = Form.elements;

            // phone
            const phone = self.getElm().getElements(
                '.quiqqer-customer-address-phoneTable tbody tr'
            ).map(function(Row) {
                const Input = Row.getElement('input');
                const Select = Row.getElement('select');

                return {
                    type: Select.value,
                    no: Input.value
                };
            });

            // email
            const mails = self.getElm().getElements(
                '.quiqqer-customer-address-emailTable input'
            ).map(function(Input) {
                return Input.value;
            }).filter(function(entry) {
                return entry;
            });

            const data = {
                company: elements['address-company'].value,
                salutation: elements['address-salutation'].value,
                firstname: elements['address-firstname'].value,
                lastname: elements['address-lastname'].value,
                street_no: elements['address-street_no'].value,
                zip: elements['address-zip'].value,
                city: elements['address-city'].value,
                country: elements['address-country'].value,
                suffix: elements['address-suffix'].value,
                mails: mails,
                phone: phone
            };

            QUIAjax.post('ajax_users_address_save', function() {
                self.fireEvent('submit', [self]);
                self.close();
            }, {
                uid: self.getAttribute('userId'),
                aid: 0,
                data: JSON.encode(data),
                onError: function() {
                    self.Loader.hide();
                }
            });
        },

        //region phone & mail

        /**
         * Add a phone entry
         *
         * @return {Element}
         */
        addPhone: function() {
            const Table = this.getElm().getElement('.quiqqer-customer-address-phoneTable');

            return new Element('tr', {
                html: '' +
                    '<td>' +
                    '    <label class="field-container">' +
                    '        <span class="field-container-item field-container-item-select">' +
                    '            <select name="phone-type">' +
                    '                <option value="tel">' + QUILocale.get('quiqqer/core', 'tel') + '</option>' +
                    '                <option value="fax">' + QUILocale.get('quiqqer/core', 'fax') + '</option>' +
                    '                <option value="mobile">' + QUILocale.get('quiqqer/core', 'mobile') +
                    '</option>' +
                    '            </select>' +
                    '        </span>' +
                    '        <input type="text" class="field-container-field" />' +
                    '    </label>' +
                    '</td>'
            }).inject(Table.getElement('tbody'));
        },

        /**
         * Add an email entry
         *
         * @return {Element}
         */
        addEmail: function() {
            const Table = this.getElm().getElement('.quiqqer-customer-address-emailTable');

            return new Element('tr', {
                html: '' +
                    '<td>' +
                    '    <label class="field-container">' +
                    '        <span class="field-container-item">' +
                    '            ' + QUILocale.get('quiqqer/core', 'email') +
                    '        </span>' +
                    '        <input type="email" class="field-container-field" />' +
                    '    </label>' +
                    '</td>'
            }).inject(Table.getElement('tbody'));
        }

        //endregion
    });
});
