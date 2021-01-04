/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/AddressEdit
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/AddressEdit', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/countries/bin/Countries',
    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/AddressEdit.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/AddressEdit.css'

], function (QUI, QUIControl, Countries, Users, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/AddressEdit',

        Binds: [
            '$onInject'
        ],

        options: {
            addressId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$data = {};

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the DOMNode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self = this;

            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-customer-address-edit');

            this.$Elm.set('html', Mustache.render(template, {
                titleAddress         : QUILocale.get('quiqqer/quiqqer', 'address'),
                textAddressCompany   : QUILocale.get('quiqqer/quiqqer', 'company'),
                textAddressSalutation: QUILocale.get('quiqqer/quiqqer', 'salutation'),
                textAddressFirstname : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                textAddressLastname  : QUILocale.get('quiqqer/quiqqer', 'lastname'),
                textAddressStreet    : QUILocale.get('quiqqer/quiqqer', 'street'),
                textAddressZIP       : QUILocale.get('quiqqer/quiqqer', 'zip'),
                textAddressCity      : QUILocale.get('quiqqer/quiqqer', 'city'),
                textAddressCountry   : QUILocale.get('quiqqer/quiqqer', 'country')
            }));

            this.getElm().getElement('[name="add-phone"]').addEvent('click', function (e) {
                e.stop();
                self.addPhone();
            });

            this.getElm().getElement('[name="add-email"]').addEvent('click', function (e) {
                e.stop();
                self.addEmail();
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            Countries.getCountries().then(function (countries) {
                for (var code in countries) {
                    if (!countries.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        html : countries[code],
                        value: code
                    }).inject(self.getElm().getElement('[name="address-country"]'));
                }

                // fetch address
                QUIAjax.get('ajax_users_address_get', function (result) {
                    var Form     = self.getElm().getElement('form'),
                        elements = Form.elements;

                    self.$data = result;

                    elements['address-salutation'].value = result.salutation;
                    elements['address-company'].value    = result.company;
                    elements['address-firstname'].value  = result.firstname;
                    elements['address-lastname'].value   = result.lastname;
                    elements['address-street_no'].value  = result.street_no;
                    elements['address-zip'].value        = result.zip;
                    elements['address-city'].value       = result.city;
                    elements['address-country'].value    = result.country;

                    var mail  = [];
                    var phone = [];

                    try {
                        mail = JSON.decode(result.mail);
                    } catch (e) {
                    }

                    mail.forEach(function (entry) {
                        self.addEmail().getElement('input').set('value', entry);
                    });

                    try {
                        phone = JSON.decode(result.phone);
                    } catch (e) {
                    }

                    phone.forEach(function (entry) {
                        var Phone = self.addPhone();

                        Phone.getElement('select').set('value', entry.type);
                        Phone.getElement('input').set('value', entry.no);
                    });

                    self.fireEvent('load', [self]);
                }, {
                    aid: self.getAttribute('addressId')
                });
            });
        },

        /**
         *
         * @return {string}
         */
        getAddressId: function () {
            if (!this.getAttribute('addressId')) {
                return '';
            }

            return this.getAttribute('addressId');
        },

        /**
         * Return the firstname of the address
         *
         * @return {string}
         */
        getFirstname: function () {
            if (typeof this.$data.firstname === 'undefined') {
                return '';
            }

            return this.$data.firstname;
        },

        /**
         * Return the lastname of the address
         *
         * @return {string}
         */
        getLastname: function () {
            if (typeof this.$data.lastname === 'undefined') {
                return '';
            }

            return this.$data.lastname;
        },

        /**
         * alias for save
         *
         * @return {*|Promise}
         */
        save: function () {
            return this.update();
        },

        /**
         * Updates the data to the address
         *
         * @return {Promise}
         */
        update: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                var Form     = self.getElm().getElement('form'),
                    elements = Form.elements;

                // phone
                var phone = self.getElm().getElements(
                    '.quiqqer-customer-address-phoneTable tbody tr'
                ).map(function (Row) {
                    var Input  = Row.getElement('input');
                    var Select = Row.getElement('select');

                    return {
                        type: Select.value,
                        no  : Input.value
                    };
                });

                // email
                var mails = self.getElm().getElements(
                    '.quiqqer-customer-address-emailTable input'
                ).map(function (Input) {
                    return Input.value;
                }).filter(function (entry) {
                    return entry;
                });


                QUIAjax.post('ajax_users_address_save', resolve, {
                    'package': 'quiqqer/customer',
                    onError  : reject,
                    aid      : self.getAttribute('addressId'),
                    data     : JSON.encode({
                        company   : elements['address-company'].value,
                        salutation: elements['address-salutation'].value,
                        firstname : elements['address-firstname'].value,
                        lastname  : elements['address-lastname'].value,
                        street_no : elements['address-street_no'].value,
                        zip       : elements['address-zip'].value,
                        city      : elements['address-city'].value,
                        country   : elements['address-country'].value,
                        mails     : mails,
                        phone     : phone
                    })
                });
            });
        },

        //region phone & mail

        /**
         * Add a phone entry
         *
         * @return {Element}
         */
        addPhone: function () {
            var Table = this.getElm().getElement('.quiqqer-customer-address-phoneTable');

            return new Element('tr', {
                html: '' +
                    '<td>' +
                    '    <label class="field-container">\n' +
                    '        <span class="field-container-item field-container-item-select">\n' +
                    '            <select name="phone-type">' +
                    '                <option value="tel">Telefon</option>' +
                    '                <option value="fax">Fax</option>' +
                    '                <option value="mobile">Mobil</option>' +
                    '            </select>' +
                    '        </span>\n' +
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
        addEmail: function () {
            var Table = this.getElm().getElement('.quiqqer-customer-address-emailTable');

            return new Element('tr', {
                html: '' +
                    '<td>' +
                    '    <label class="field-container">\n' +
                    '        <span class="field-container-item">\n' +
                    '            E-Mail' +
                    '        </span>\n' +
                    '        <input type="email" class="field-container-field" />' +
                    '    </label>' +
                    '</td>'
            }).inject(Table.getElement('tbody'));
        }


        //endregion
    });
});
