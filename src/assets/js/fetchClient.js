(function (global) {
    'use strict';

    function noop() {}

    function FetchClient(config) {
        const opts = config || {};
        this.task = typeof opts.task === 'function' ? opts.task : noop;
        this.interval = Math.max(Number(opts.interval) || 0, 1000);
        this.options = Object.assign({
            immediate: false,
            runOnFocus: true
        }, opts);

        this.timer = null;
        this.active = false;
        this.visibilityHandler = this.onVisibilityChange.bind(this);
        this.focusHandler = this.onFocus.bind(this);
    }

    FetchClient.prototype.safeRun = function () {
        try {
            this.task();
        } catch (err) {
            console.error('HavenCoreFetchClient task error:', err);
        }
    };

    FetchClient.prototype.onVisibilityChange = function () {
        if (document.hidden) {
            this.pause();
        } else if (this.active) {
            if (this.options.runOnFocus !== false) {
                this.safeRun();
            }
            this.resume();
        }
    };

    FetchClient.prototype.onFocus = function () {
        if (!document.hidden && this.active) {
            if (this.options.runOnFocus !== false) {
                this.safeRun();
            }
            this.resume();
        }
    };

    FetchClient.prototype.pause = function () {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    };

    FetchClient.prototype.resume = function () {
        if (!this.active || document.hidden || this.timer) {
            return;
        }
        this.timer = setInterval(() => {
            if (!document.hidden) {
                this.safeRun();
            }
        }, this.interval);
    };

    FetchClient.prototype.start = function (override) {
        if (this.active) {
            return;
        }
        if (override && typeof override === 'object') {
            Object.assign(this.options, override);
        }

        this.active = true;
        document.addEventListener('visibilitychange', this.visibilityHandler);
        window.addEventListener('blur', this.visibilityHandler);
        window.addEventListener('focus', this.focusHandler);

        if (!document.hidden && this.options.immediate) {
            this.safeRun();
        }

        this.resume();
    };

    FetchClient.prototype.stop = function () {
        if (!this.active) {
            return;
        }
        this.active = false;
        this.pause();
        document.removeEventListener('visibilitychange', this.visibilityHandler);
        window.removeEventListener('blur', this.visibilityHandler);
        window.removeEventListener('focus', this.focusHandler);
    };

    FetchClient.prototype.triggerNow = function () {
        this.safeRun();
    };

    global.HavenCoreFetchClient = {
        create(options) {
            return new FetchClient(options);
        }
    };
})(window);
