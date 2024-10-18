var BigBrother = function(config) {
    config = config || {};
    this.waitTime = 100;
    BigBrother.superclass.constructor.call(this,config);
};
Ext.extend(BigBrother,Ext.Component,{
    page:{},window:{},grid:{},tree:{},panel:{},tabs:{},combo:{},
    config: {
        connectorUrl: ''
    },
    attribution: function() {
        return {
            xtype: 'panel',
            bodyStyle: 'text-align: right; background: none; padding: 10px 0;',
            html: '<p class="bigbrother-credits">' +
                '<span class="bigbrother-credits__version">BigBrother v' + BigBrother.config.version + '</span>' +
                '<a href="https://www.modmore.com/extras/bigbrother/?utm_source=bigbrother_footer" target="_blank" rel="noopener"  class="bigbrother-credits__logo">' +
                    '<img src="' + BigBrother.config.assetsUrl + 'images/modmore.svg" alt="a modmore product"/>' +
                '</a>' +
            '</p>',
            border: false,
            anchor: '100%'
        };
    },

    _charts: [],
    _keys: [],

    registerCharts(charts) {
        charts.forEach((ch) => {
            this._charts.push(ch);
            this._keys.push(ch.key);
        });

        this.debounce(this.refreshCharts);
    },

    debounce(func, timeout = this.waitTime){
        clearTimeout(this._refreshTimer);
        this._refreshTimer = setTimeout(func.bind(this), timeout);
    },

    _spinners: null,
    enableSpinners() {
        if (!this._spinners) {
            this._spinners = document.querySelectorAll('.bigbrother-spinner');
        }

        this._spinners.forEach((spinner) => {
            spinner.style.display = 'initial';
        })
    },

    disableSpinners() {
        if (!this._spinners) {
            this._spinners = document.querySelectorAll('.bigbrother-spinner');
        }

        this._spinners.forEach((spinner) => {
            spinner.style.display = 'none';
        })
    },

    renderPeriodDates(visitsChart) {
        if (visitsChart['first_date'] && visitsChart['last_date']) {
            let period = this.renderDate(visitsChart['first_date']) + ' - ' + this.renderDate(visitsChart['last_date']);
            let element = document.querySelector('#bb-title-period');
            if (typeof element !== 'undefined' && element !== null) {
                element.innerHTML = period;
            }
        }
    },

    refreshCharts: function () {
        this.enableSpinners();
        MODx.Ajax.request({
            url: BigBrother.config.connectorUrl,
            params: {
                action: 'mgr/reports',
                reports: this._keys.join(',')
            },
            method: 'GET',
            scope: this,
            listeners: {
                success: {
                    fn: function (result) {
                        if (result.data['visits/line']) {
                            this.renderPeriodDates(result.data['visits/line']);
                        }

                        this._charts.forEach((ch) => {
                            if (result.data[ch.key]) {
                                try {
                                    ch.setData(result.data[ch.key]);
                                } catch (err) {
                                    console.error('Failed rendering widget "' + ch.key + '": ', err);
                                }
                            }
                        });
                        this.disableSpinners();
                    },
                    scope: this
                },
                failure: {
                    fn: function (result) {
                        Ext.MessageBox.alert(_('error'), result.responseText);
                        this.disableSpinners();
                    },
                    scope: this
                }
            }
        });
    },

    /**
     * Renders date in correct locale and format
     * @param date - parameter format must be yyyy-MM-dd (Y-m-d in PHP)
     * @returns {*}
     */
    renderDate: function(date) {
        return luxon.DateTime.fromFormat(date, 'yyyy-MM-dd').setLocale(BigBrother.locale()).toFormat(BigBrother.dateFormat());
    },

    /**
     * Static method to get system date format converted into Luxon format to be used by chart.js
     * @returns {string}
     */
    dateFormat: function() {
        let format = BigBrother.phpToLuxonFormat(MODx.config.manager_date_format);
        // Only add the day of the week name if it isn't already specified in the date format
        if (!format.includes('c') && !format.includes('E')) {
            return `cccc ${format}`;
        }

        return format;
    },

    /**
     * Checks if manager_language exists for 2.x, otherwise uses the manager cultureKey
     * Falls back to 'en'
     * @returns {string}
     */
    locale: function() {
        return typeof MODx.config.manager_language !== 'undefined'
        && MODx.config.manager_language !== null
        && MODx.config.manager_language !== ''
            ? MODx.config.manager_language || 'en'
            : MODx.config.cultureKey || 'en';
    },

    /**
     * Converts a PHP date format string to a Luxon date format string.
     * https://gist.github.com/mahmoudsaeed/83f9ca33056647262a3e046a7b2351fc
     *
     * @param {string} format - The PHP date format string to convert.
     * @param {boolean} [standalone=false] - Optional. Whether to use standalone tokens for month and weekday names. Defaults to false.
     *
     * @returns {string} The converted Luxon date format string.
     */
    phpToLuxonFormat: function(format, standalone = false) {
        const replacements = {
            // Day
            d: 'dd',
            D: standalone ? 'ccc' : 'EEE',
            j: 'd',
            l: standalone ? 'cccc' : 'EEEE',
            N: standalone ? 's' : 'E',
            S: '', // no equivalent
            w: '', // no equivalent, use N
            z: 'o',
            // Week
            W: 'W',
            // Month
            F: standalone ? 'LLLL' : 'MMMM',
            m: standalone ? 'LL' : 'MM',
            M: standalone ? 'LLL' : 'MMM',
            n: standalone ? 'L' : 'M',
            t: '', // no equivalent
            // Year
            L: '', // no equivalent
            o: 'kkkk',
            X: '', // no equivalent
            x: '', // no equivalent
            Y: 'yyyy',
            y: 'yy',
            // Time
            a: 'a',
            A: 'a', // close enough
            B: '', // no equivalent
            g: 'h',
            G: 'H',
            h: 'hh',
            H: 'HH',
            i: 'mm',
            s: 'ss',
            u: '', // no equivalent, use v
            v: 'SSS',
            // Timezone
            e: 'z',
            I: '', // no equivalent
            O: 'ZZZ',
            P: 'ZZ', // no equivalent
            p: '', // no equivalent, use P
            T: 'ZZZZ',
            Z: '', // no equivalent
            // Full Date/Time
            c: "yyyy-LL-dd'T'HH:mm:ssZZ",
            r: 'EEE, dd LLL yyyy HH:mm:ss ZZZ',
            U: 'X',
        }

        return format
            .split('')
            .map((chr) => (chr in replacements ? replacements[chr] : chr))
            .join('')
    }
});
Ext.reg('bigbrother',BigBrother);
BigBrother = new BigBrother();
