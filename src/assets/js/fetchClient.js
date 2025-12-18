(function (global) {
    'use strict';

    function noop() {}

    const globalConfig = {
        debug: !!(global.HavenCoreFetchClientConfig && global.HavenCoreFetchClientConfig.debug)
    };
    const instances = new Set();
    const instanceRegistry = new Map();

    function FetchClient(config) {
        const opts = config || {};
        this.task = typeof opts.task === 'function' ? opts.task : noop;
        this.interval = Math.max(Number(opts.interval) || 0, 1000);
        this.options = Object.assign({
            immediate: false,
            runOnFocus: true
        }, opts);
        this.debug = typeof opts.debug === 'boolean' ? opts.debug : globalConfig.debug;

        this.timer = null;
        this.active = false;
        this.visibilityHandler = this.onVisibilityChange.bind(this);
        this.focusHandler = this.onFocus.bind(this);
        this.id = typeof opts.id === 'string' && opts.id.trim() ? opts.id.trim() : null;

        instances.add(this);
        if (this.id) {
            instanceRegistry.set(this.id, this);
        }
    }

    FetchClient.prototype.log = function (...args) {
        if (this.debug) {
            console.debug('[HavenCoreFetchClient]', ...args);
        }
    };

    FetchClient.prototype.safeRun = function () {
        try {
            this.task();
        } catch (err) {
            console.error('HavenCoreFetchClient task error:', err);
        }
    };

    FetchClient.prototype.onVisibilityChange = function () {
        if (document.hidden) {
            this.log('document hidden -> pause');
            this.pause();
        } else if (this.active) {
            this.log('document visible -> resume');
            if (this.options.runOnFocus !== false) {
                this.safeRun();
            }
            this.resume();
        }
    };

    FetchClient.prototype.onFocus = function () {
        if (!document.hidden && this.active) {
            this.log('window focus -> resume');
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
            this.log('polling paused');
        }
    };

    FetchClient.prototype.resume = function () {
        if (!this.active || document.hidden || this.timer) {
            return;
        }
        this.log('polling resumed');
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

        this.log('poller started');
        if (!document.hidden && this.options.immediate) {
            this.safeRun();
        }

        this.resume();
    };

    FetchClient.prototype.stop = function () {
        if (!this.active) {
            instances.delete(this);
            if (this.id && instanceRegistry.get(this.id) === this) {
                instanceRegistry.delete(this.id);
            }
            return;
        }
        this.active = false;
        this.pause();
        document.removeEventListener('visibilitychange', this.visibilityHandler);
        window.removeEventListener('blur', this.visibilityHandler);
        window.removeEventListener('focus', this.focusHandler);
        instances.delete(this);
        if (this.id && instanceRegistry.get(this.id) === this) {
            instanceRegistry.delete(this.id);
        }
        this.log('poller stopped');
    };

    FetchClient.prototype.triggerNow = function () {
        this.safeRun();
    };

    global.HavenCoreFetchClient = {
        create(options) {
            const opts = options || {};
            if (opts && typeof opts.id === 'string') {
                const key = opts.id.trim();
                if (key) {
                    const existing = instanceRegistry.get(key);
                    if (existing) {
                        existing.stop();
                    }
                }
            }
            return new FetchClient(opts);
        },
        stopById(id) {
            if (typeof id !== 'string') {
                return;
            }
            const key = id.trim();
            if (!key) {
                return;
            }
            const existing = instanceRegistry.get(key);
            if (existing) {
                existing.stop();
            }
        },
        setDebug(value) {
            globalConfig.debug = !!value;
            instances.forEach(instance => {
                instance.debug = globalConfig.debug;
            });
        },
        getDebug() {
            return globalConfig.debug;
        }
    };
})(window);
