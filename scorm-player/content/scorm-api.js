(function () {
    'use strict';

    var AUTO_COMMIT_MS = 30000;

    var ERRORS_12 = {
        0: 'No error',
        101: 'General exception',
        201: 'Invalid argument error',
        202: 'Element cannot have children',
        203: 'Element not an array - cannot have count',
        301: 'Not initialized',
        401: 'Not implemented error',
        402: 'Invalid set value, element is a keyword',
        403: 'Element is read only',
        404: 'Element is write only',
        405: 'Incorrect data type'
    };

    var ERRORS_2004 = {
        0: 'No error',
        101: 'General exception',
        102: 'General initialization failure',
        103: 'Already initialized',
        104: 'Content instance terminated',
        111: 'General termination failure',
        112: 'Termination before initialization',
        113: 'Termination after termination',
        122: 'Retrieve data before initialization',
        123: 'Retrieve data after termination',
        132: 'Store data before initialization',
        133: 'Store data after termination',
        142: 'Commit before initialization',
        143: 'Commit after termination',
        201: 'General argument error',
        301: 'General get failure',
        351: 'General set failure',
        391: 'General commit failure',
        401: 'Undefined data model element',
        402: 'Unimplemented data model element',
        403: 'Data model element value not initialized',
        404: 'Data model element is read only',
        405: 'Data model element is write only',
        406: 'Data model element type mismatch',
        407: 'Data model element value out of range',
        408: 'Data model dependency not established'
    };

    var CODES = {
        '1.2': {
            alreadyInitialized: 101, initAfterTerminate: 101, notInitialized: 301, terminated: 101,
            getBeforeInit: 301, getAfterTerminate: 101, setBeforeInit: 301, setAfterTerminate: 101,
            commitBeforeInit: 301, commitAfterTerminate: 101, terminateBeforeInit: 301, terminateAfterTerminate: 101,
            argument: 201, undefined: 201, noChildren: 202, noCount: 203, keyword: 402,
            readOnly: 403, writeOnly: 404, type: 405, range: 405, tooLong: 405, index: 201, dependency: 201, notInitializedValue: 0
        },
        '2004': {
            alreadyInitialized: 103, initAfterTerminate: 104, terminated: 104,
            getBeforeInit: 122, getAfterTerminate: 123, setBeforeInit: 132, setAfterTerminate: 133,
            commitBeforeInit: 142, commitAfterTerminate: 143, terminateBeforeInit: 112, terminateAfterTerminate: 113,
            argument: 201, undefined: 401, noChildren: 301, noCount: 301, keyword: 404,
            readOnly: 404, writeOnly: 405, type: 406, range: 407, tooLong: 351, index: 351, dependency: 408, notInitializedValue: 403
        }
    };

    var STATUS_ELEMENTS = ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'];

    function Runtime(init, send) {
        this.version = init.version === '2004' ? '2004' : '1.2';
        this.definitions = init.definitions || {};
        this.cmi = init.cmi || {};
        this.review = this.cmi['cmi.mode'] === 'review' || this.cmi['cmi.core.lesson_mode'] === 'review';
        this.codes = CODES[this.version];
        this.messages = this.version === '2004' ? ERRORS_2004 : ERRORS_12;
        this.send = send;
        this.state = 'new';
        this.dirty = {};
        this.lastError = 0;
        this.startedAt = 0;
        this.timer = null;
    }

    Runtime.prototype.fail = function (code, result) {
        this.lastError = code;
        return result;
    };

    Runtime.prototype.ok = function (result) {
        this.lastError = 0;
        return result;
    };

    Runtime.prototype.initialize = function (param) {
        if (param !== '' && param !== undefined) {
            return this.fail(this.codes.argument, 'false');
        }
        if (this.state === 'running') {
            return this.fail(this.codes.alreadyInitialized, 'false');
        }
        if (this.state === 'terminated') {
            return this.fail(this.codes.initAfterTerminate, 'false');
        }

        this.state = 'running';
        this.startedAt = Date.now();
        this.timer = setInterval(this.flush.bind(this), AUTO_COMMIT_MS);
        return this.ok('true');
    };

    Runtime.prototype.terminate = function (param) {
        if (param !== '' && param !== undefined) {
            return this.fail(this.codes.argument, 'false');
        }
        if (this.state === 'new') {
            return this.fail(this.codes.terminateBeforeInit, 'false');
        }
        if (this.state === 'terminated') {
            return this.fail(this.codes.terminateAfterTerminate, 'false');
        }

        this.state = 'terminated';
        clearInterval(this.timer);
        this.send({
            type: 'terminate',
            values: this.takeDirty(),
            elapsed: Math.round((Date.now() - this.startedAt) / 1000)
        });
        return this.ok('true');
    };

    Runtime.prototype.commit = function (param) {
        if (param !== '' && param !== undefined) {
            return this.fail(this.codes.argument, 'false');
        }
        if (this.state === 'new') {
            return this.fail(this.codes.commitBeforeInit, 'false');
        }
        if (this.state === 'terminated') {
            return this.fail(this.codes.commitAfterTerminate, 'false');
        }

        this.flush();
        return this.ok('true');
    };

    Runtime.prototype.getValue = function (element) {
        if (this.state === 'new') {
            return this.fail(this.codes.getBeforeInit, '');
        }
        if (this.state === 'terminated') {
            return this.fail(this.codes.getAfterTerminate, '');
        }
        if (typeof element !== 'string' || element === '') {
            return this.fail(this.version === '2004' ? 301 : 201, '');
        }

        var definition = this.definition(element);
        if (!definition) {
            return this.fail(this.undefinedCode(element), '');
        }

        var access = definition[0], type = definition[1];
        if (access === 'w') {
            return this.fail(this.codes.writeOnly, '');
        }
        if (type === 'keyword') {
            return this.ok(definition[2]);
        }
        if (type === 'count') {
            return this.ok(String(this.count(element.slice(0, -'._count'.length))));
        }
        if (!Object.prototype.hasOwnProperty.call(this.cmi, element)) {
            return this.fail(this.codes.notInitializedValue, '');
        }

        return this.ok(String(this.cmi[element]));
    };

    Runtime.prototype.setValue = function (element, value) {
        if (this.state === 'new') {
            return this.fail(this.codes.setBeforeInit, 'false');
        }
        if (this.state === 'terminated') {
            return this.fail(this.codes.setAfterTerminate, 'false');
        }
        if (typeof element !== 'string' || element === '') {
            return this.fail(this.version === '2004' ? 351 : 201, 'false');
        }

        var definition = this.definition(element);
        if (!definition) {
            return this.fail(this.undefinedCode(element), 'false');
        }
        if (definition[1] === 'keyword' || definition[1] === 'count') {
            return this.fail(this.codes.keyword, 'false');
        }
        if (definition[0] === 'r') {
            return this.fail(this.codes.readOnly, 'false');
        }

        value = String(value);
        var typeError = this.checkType(definition, value);
        if (typeError) {
            return this.fail(this.codes[typeError], 'false');
        }

        var structureError = this.checkStructure(element);
        if (structureError) {
            return this.fail(this.codes[structureError], 'false');
        }

        this.cmi[element] = value;
        if (!this.review) {
            this.dirty[element] = value;
        }
        if (STATUS_ELEMENTS.indexOf(element) !== -1) {
            this.send({ type: 'status', status: this.displayStatus() });
        }

        return this.ok('true');
    };

    Runtime.prototype.getLastError = function () {
        return String(this.lastError);
    };

    Runtime.prototype.getErrorString = function (code) {
        return this.messages[parseInt(code, 10)] || '';
    };

    Runtime.prototype.getDiagnostic = function (code) {
        return this.getErrorString(code === '' || code === undefined ? this.lastError : code);
    };

    Runtime.prototype.definition = function (element) {
        return this.definitions[element.replace(/\.\d+(?=\.)/g, '.n')] || null;
    };

    Runtime.prototype.undefinedCode = function (element) {
        if (/\._children$/.test(element)) {
            return this.codes.noChildren;
        }
        if (/\._count$/.test(element)) {
            return this.codes.noCount;
        }
        return this.codes.undefined;
    };

    Runtime.prototype.count = function (prefix) {
        var pattern = new RegExp('^' + prefix.replace(/\./g, '\\.') + '\\.(\\d+)\\.');
        var max = -1;
        Object.keys(this.cmi).forEach(function (key) {
            var match = pattern.exec(key);
            if (match) {
                max = Math.max(max, parseInt(match[1], 10));
            }
        });
        return max + 1;
    };

    Runtime.prototype.checkStructure = function (element) {
        var parts = element.split('.');
        for (var i = 0; i < parts.length; i++) {
            if (/^\d+$/.test(parts[i])) {
                var prefix = parts.slice(0, i).join('.');
                var index = parseInt(parts[i], 10);
                if (index > this.count(prefix)) {
                    return 'index';
                }
                if (this.version === '2004' && parts[i + 1] !== 'id'
                    && /^cmi\.(objectives|interactions)$/.test(prefix)
                    && !Object.prototype.hasOwnProperty.call(this.cmi, prefix + '.' + index + '.id')) {
                    return 'dependency';
                }
            }
        }
        return null;
    };

    Runtime.prototype.checkType = function (definition, value) {
        var type = definition[1], param = definition[2];
        var number = Number(value);
        var isNumber = value.trim() !== '' && isFinite(number);

        switch (type) {
            case 'string':
                return value.length <= param ? null : 'tooLong';
            case 'vocab':
                return param.indexOf(value) !== -1 ? null : 'type';
            case 'decimal':
                if (value === '') {
                    return null;
                }
                return isNumber ? inRange(number, param) : 'type';
            case 'int':
                return /^-?\d+$/.test(value) ? inRange(number, param) : 'type';
            case 'real':
                return isNumber ? inRange(number, param) : 'type';
            case 'timespan':
                return /^\d{2,4}:\d{2}:\d{2}(\.\d{1,2})?$/.test(value) ? null : 'type';
            case 'time':
                return /^\d{2}:\d{2}:\d{2}(\.\d{1,2})?$/.test(value) ? null : 'type';
            case 'duration':
                return /^P(?=.)(\d+Y)?(\d+M)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d{1,2})?S)?)?$/.test(value) ? null : 'type';
            case 'timestamp':
                return /^\d{4}(-\d{2}(-\d{2}(T\d{2}(:\d{2}(:\d{2}(\.\d{1,2})?)?)?(Z|[+-]\d{2}(:\d{2})?)?)?)?)?$/.test(value) ? null : 'type';
            case 'result':
                return param.indexOf(value) !== -1 || isNumber ? null : 'type';
            default:
                return 'type';
        }
    };

    function inRange(number, range) {
        if (!range) {
            return null;
        }
        if ((range[0] !== null && number < range[0]) || (range[1] !== null && number > range[1])) {
            return 'range';
        }
        return null;
    }

    Runtime.prototype.displayStatus = function () {
        var success = this.cmi['cmi.success_status'];
        if (success && success !== 'unknown') {
            return success;
        }
        var completion = this.cmi['cmi.completion_status'];
        if (completion && completion !== 'unknown') {
            return completion;
        }
        return this.cmi['cmi.core.lesson_status'] || 'not attempted';
    };

    Runtime.prototype.takeDirty = function () {
        var values = this.dirty;
        this.dirty = {};
        return values;
    };

    Runtime.prototype.flush = function () {
        var values = this.takeDirty();
        if (Object.keys(values).length > 0) {
            this.send({ type: 'commit', values: values });
        }
    };

    function scorm12(runtime) {
        return {
            LMSInitialize: function (p) { return runtime.initialize(p); },
            LMSFinish: function (p) { return runtime.terminate(p); },
            LMSGetValue: function (e) { return runtime.getValue(e); },
            LMSSetValue: function (e, v) { return runtime.setValue(e, v); },
            LMSCommit: function (p) { return runtime.commit(p); },
            LMSGetLastError: function () { return runtime.getLastError(); },
            LMSGetErrorString: function (c) { return runtime.getErrorString(c); },
            LMSGetDiagnostic: function (c) { return runtime.getDiagnostic(c); }
        };
    }

    function scorm2004(runtime) {
        return {
            Initialize: function (p) { return runtime.initialize(p); },
            Terminate: function (p) { return runtime.terminate(p); },
            GetValue: function (e) { return runtime.getValue(e); },
            SetValue: function (e, v) { return runtime.setValue(e, v); },
            Commit: function (p) { return runtime.commit(p); },
            GetLastError: function () { return runtime.getLastError(); },
            GetErrorString: function (c) { return runtime.getErrorString(c); },
            GetDiagnostic: function (c) { return runtime.getDiagnostic(c); }
        };
    }

    var body = document.body;
    var playerOrigin = body.getAttribute('data-player-origin');
    var launch = body.getAttribute('data-launch');
    var frame = document.getElementById('sco');
    var runtime = null;

    function send(message) {
        message.scorm = true;
        window.parent.postMessage(message, playerOrigin);
    }

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (event.source !== window.parent || event.origin !== playerOrigin || !data || data.scorm !== true) {
            return;
        }
        if (data.type !== 'init' || runtime) {
            return;
        }

        runtime = new Runtime(data, send);
        if (runtime.version === '2004') {
            window.API_1484_11 = scorm2004(runtime);
        } else {
            window.API = scorm12(runtime);
        }
        frame.src = launch;
    });

    window.addEventListener('pagehide', function () {
        if (runtime && runtime.state === 'running') {
            runtime.flush();
        }
    });

    send({ type: 'ready' });
})();
