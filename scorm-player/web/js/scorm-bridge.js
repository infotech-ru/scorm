(function () {
    'use strict';

    var config = JSON.parse(document.getElementById('scorm-config').textContent);
    var frame = document.getElementById('content-frame');
    var queue = Promise.resolve();
    var terminated = false;

    function showError(message) {
        var box = document.getElementById('error');
        box.textContent = message;
        box.hidden = false;
    }

    function notifyMainApp(message) {
        if (window.parent !== window) {
            window.parent.postMessage(message, config.mainAppOrigin);
        }
    }

    function request(action, payload, keepalive) {
        return fetch(config.apiEndpoint + '/' + action, {
            method: 'POST',
            credentials: 'omit',
            keepalive: keepalive,
            headers: {
                'Authorization': 'Bearer ' + config.token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function enqueue(action, payload) {
        var keepalive = action === 'terminate';
        queue = queue
            .then(function () { return request(action, payload, keepalive); })
            .catch(function (error) {
                showError('Progress could not be saved (' + error.message + '). Please reload the course.');
            });
        return queue;
    }

    function isPlainValues(values) {
        return values !== null && typeof values === 'object' && !Array.isArray(values);
    }

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (event.source !== frame.contentWindow || event.origin !== config.contentOrigin || !data || data.scorm !== true) {
            return;
        }

        switch (data.type) {
            case 'ready':
                frame.contentWindow.postMessage({
                    scorm: true,
                    type: 'init',
                    version: config.version,
                    definitions: config.definitions,
                    cmi: config.cmi
                }, config.contentOrigin);
                break;
            case 'commit':
                if (!terminated && isPlainValues(data.values)) {
                    enqueue('commit', { values: data.values });
                }
                break;
            case 'terminate':
                if (!terminated && isPlainValues(data.values)) {
                    terminated = true;
                    enqueue('terminate', { values: data.values, elapsed: Number(data.elapsed) || 0 })
                        .then(function () { notifyMainApp({ type: 'scorm-terminate' }); });
                }
                break;
            case 'status':
                if (typeof data.status === 'string') {
                    notifyMainApp({ type: 'scorm-status', status: data.status });
                }
                break;
        }
    });

    frame.src = config.hostUrl;
})();
