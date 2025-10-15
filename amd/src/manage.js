// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see https://www.gnu.org/licenses/.

define(['core/ajax', 'core/str'], function(Ajax, Str) {
    'use strict';

    function showAlert(message, ok) {
        var el = document.getElementById('asyntai-alert');
        if (!el) {
            return;
        }
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
    }

    function generateState() {
        return 'moodle_' + Math.random().toString(36).substr(2, 9);
    }

    function updateFallbackLink(currentState) {
        var fallbackLink = document.getElementById('asyntai-fallback-link');
        if (fallbackLink && currentState) {
            fallbackLink.href = 'https://asyntai.com/moodle-auth?platform=moodle&state=' + encodeURIComponent(currentState);
        }
    }

    function pollForConnection(expectedOrigin, state, onDone) {
        var attempts = 0;
        function check() {
            if (attempts++ > 60) {
                return;
            }
            var script = document.createElement('script');
            var cb = 'asyntai_cb_' + Date.now();
            var origin = expectedOrigin || 'https://asyntai.com';
            script.src = origin + '/connect-status.js?state=' + encodeURIComponent(state) + '&cb=' + cb;
            window[cb] = function(data) {
                try { delete window[cb]; } catch (e) {}
                if (data && data.site_id) {
                    onDone(data);
                    return;
                }
                setTimeout(check, 500);
            };
            script.onerror = function() {
                setTimeout(check, 1000);
            };
            document.head.appendChild(script);
        }
        setTimeout(check, 800);
    }

    function updateStatusUI(strings, accountEmail, resetUrl) {
        var status = document.getElementById('asyntai-status');
        if (!status) {
            return;
        }
        var html = strings.status + ': <span style="color:#008a20;">' + strings.connected + '</span>';
        if (accountEmail) {
            html += ' ' + strings.as + ' ' + accountEmail + ' <a class="btn btn-secondary" style="margin-left:8px;" href="' + (resetUrl || '#') + '">' + strings.reset + '</a>';
        }
        status.innerHTML = html;

        var box = document.getElementById('asyntai-connected-box');
        if (box) { box.style.display = 'block'; }
        var wrap = document.getElementById('asyntai-popup-wrap');
        if (wrap) { wrap.style.display = 'none'; }
    }

    function getStrings() {
        return Str.get_strings([
            {key: 'popupblocked', component: 'local_asyntai'},
            {key: 'saving', component: 'local_asyntai'},
            {key: 'enabled', component: 'local_asyntai'},
            {key: 'savefailed', component: 'local_asyntai'},
            {key: 'couldnotsave', component: 'local_asyntai'},
            {key: 'status', component: 'local_asyntai'},
            {key: 'connected', component: 'local_asyntai'},
            {key: 'as', component: 'local_asyntai'},
            {key: 'reset', component: 'local_asyntai'}
        ]).then(function(s) {
            return {
                popupblocked: s[0],
                saving: s[1],
                enabled: s[2],
                savefailed: s[3],
                couldnotsave: s[4],
                status: s[5],
                connected: s[6],
                as: s[7],
                reset: s[8]
            };
        });
    }

    function init(params) {
        params = params || {};

        var currentState = generateState();
        updateFallbackLink(currentState);

        document.addEventListener('click', function(ev) {
            var t = ev.target;
            if (t && t.id === 'asyntai-connect-btn') {
                ev.preventDefault();
                getStrings().then(function(strings) {
                    var base = params.connectUrl || '';
                    if (!base) { return; }
                    var url = base + (base.indexOf('?') > -1 ? '&' : '?') + 'state=' + encodeURIComponent(currentState);
                    var w = 800, h = 720;
                    var y = window.top.outerHeight / 2 + window.top.screenY - (h / 2);
                    var x = window.top.outerWidth / 2 + window.top.screenX - (w / 2);
                    var pop = window.open(url, 'asyntai_connect', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' + w + ',height=' + h + ',top=' + y + ',left=' + x);
                    if (!pop) {
                        showAlert(strings.popupblocked, false);
                        return;
                    }
                    pollForConnection(params.expectedOrigin, currentState, function(data) {
                        showAlert(strings.saving, true);
                        Ajax.call([{
                            methodname: 'local_asyntai_save_connection',
                            args: {
                                siteid: data.site_id || '',
                                scripturl: data.script_url || '',
                                accountemail: data.account_email || ''
                            }
                        }])[0].then(function(response) {
                            updateStatusUI(strings, data.account_email, params.resetUrl);
                            showAlert(strings.enabled, true);
                        }).catch(function(err) {
                            if (window.console && console.error) {
                                console.error('[Asyntai] save error', err);
                            }
                            var msg = strings.couldnotsave.replace('{$a}', (err && err.message) || err);
                            showAlert(msg, false);
                        });
                    });
                });
            }
            if (t && t.id === 'asyntai-fallback-link') {
                ev.preventDefault();
                currentState = generateState();
                updateFallbackLink(currentState);
                // Open the link in a new window
                var url = 'https://asyntai.com/moodle-auth?platform=moodle&state=' + encodeURIComponent(currentState);
                window.open(url, '_blank', 'noopener,noreferrer');
                setTimeout(function() {
                    pollForConnection(params.expectedOrigin, currentState, function(data) {
                        getStrings().then(function(strings) {
                            showAlert(strings.saving, true);
                            Ajax.call([{
                                methodname: 'local_asyntai_save_connection',
                                args: {
                                    siteid: data.site_id || '',
                                    scripturl: data.script_url || '',
                                    accountemail: data.account_email || ''
                                }
                            }])[0].then(function(response) {
                                updateStatusUI(strings, data.account_email, params.resetUrl);
                                showAlert(strings.enabled, true);
                            }).catch(function(err) {
                                if (window.console && console.error) {
                                    console.error('[Asyntai] save error', err);
                                }
                                var msg = strings.couldnotsave.replace('{$a}', (err && err.message) || err);
                                showAlert(msg, false);
                            });
                        });
                    });
                }, 1000);
            }
        });
    }

    return {
        init: init
    };
});


