/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  UI interaction
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009,2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

var SN = { // StatusNet
    C: { // Config
        I: { // Init
            CounterBlackout: false,
            MaxLength: 140,
            PatternUsername: /^[0-9a-zA-Z\-_.]*$/,
            HTTP20x30x: [200, 201, 202, 203, 204, 205, 206, 300, 301, 302, 303, 304, 305, 306, 307],
        },

        /**
         * @fixme are these worth the trouble? They seem to mostly just duplicate
         * themselves while slightly obscuring the actual selector, so it's hard
         * to pop over to the HTML and find something.
         *
         * In theory, minification could reduce them to shorter variable names,
         * but at present that doesn't happen with yui-compressor.
         */
        S: { // Selector
            Disabled: 'disabled',
            Warning: 'warning',
            Error: 'error',
            Success: 'success',
            Processing: 'processing',
            CommandResult: 'command_result',
            FormNotice: 'form_notice',
            NoticeDataGeo: 'notice_data-geo',
            NoticeDataGeoCookie: 'NoticeDataGeo',
            NoticeDataGeoSelected: 'notice_data-geo_selected',
        }
    },

    V: {    // Variables
        // These get set on runtime via inline scripting, so don't put anything here.
    },

    /**
     * list of callbacks, categorized into _callbacks['event_name'] = [ callback_function_1, callback_function_2 ]
     *
     * @access private
     */
    _callbacks: {},

    /**
     * Map of localized message strings exported to script from the PHP
     * side via Action::getScriptMessages().
     *
     * Retrieve them via SN.msg(); this array is an implementation detail.
     *
     * @access private
     */
    messages: {},

    /**
     * Grabs a localized string that's been previously exported to us
     * from server-side code via Action::getScriptMessages().
     *
     * @example alert(SN.msg('coolplugin-failed'));
     *
     * @param {String} key: string key name to pull from message index
     * @return matching localized message string
     */
    msg: function (key) {
        if (SN.messages[key] === undefined) {
            return '[' + key + ']';
        }
        return SN.messages[key];
    },

    U: { // Utils
        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on the new notice form.
         *
         * @param {jQuery} form: jQuery object whose first matching element is the form
         * @access private
         */
        FormNoticeEnhancements: function (form) {
            if ($.data(form[0], 'ElementData') === undefined) {
                var MaxLength = form.find('.count').text();
                if (MaxLength === undefined) {
                    MaxLength = SN.C.I.MaxLength;
                }
                $.data(form[0], 'ElementData', {MaxLength: MaxLength});

                SN.U.Counter(form);

                var NDT = form.find('.notice_data-text:first');

                NDT.on('keyup', function (e) {
                    SN.U.Counter(form);
                });

                var delayedUpdate = function (e) {
                    // Cut and paste events fire *before* the operation,
                    // so we need to trigger an update in a little bit.
                    // This would be so much easier if the 'change' event
                    // actually fired every time the value changed. :P
                    window.setTimeout(function () {
                        SN.U.Counter(form);
                    }, 50);
                };
                // Note there's still no event for mouse-triggered 'delete'.
                NDT.on('cut', delayedUpdate)
                    .on('paste', delayedUpdate);
            } else {
                form.find('.count').text($.data(form[0], 'ElementData').MaxLength);
            }
        },

        /**
         * To be called from event handlers on the notice import form.
         * Triggers an update of the remaining-characters counter.
         *
         * Additional counter updates will be suppressed during the
         * next half-second to avoid flooding the layout engine with
         * updates, followed by another automatic check.
         *
         * The maximum length is pulled from data established by
         * FormNoticeEnhancements.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @access private
         */
        Counter: function (form) {
            SN.C.I.FormNoticeCurrent = form;

            var MaxLength = $.data(form[0], 'ElementData').MaxLength;

            if (MaxLength <= 0) {
                return;
            }

            var remaining = MaxLength - SN.U.CharacterCount(form);
            var counter = form.find('.count');

            if (remaining.toString() != counter.text()) {
                if (!SN.C.I.CounterBlackout || remaining === 0) {
                    if (counter.text() != String(remaining)) {
                        counter.text(remaining);
                    }
                    if (remaining < 0) {
                        form.addClass(SN.C.S.Warning);
                    } else {
                        form.removeClass(SN.C.S.Warning);
                    }
                    // Skip updates for the next 500ms.
                    // On slower hardware, updating on every keypress is unpleasant.
                    if (!SN.C.I.CounterBlackout) {
                        SN.C.I.CounterBlackout = true;
                        SN.C.I.FormNoticeCurrent = form;
                        window.setTimeout("SN.U.ClearCounterBlackout(SN.C.I.FormNoticeCurrent);", 500);
                    }
                }
            }
        },

        /**
         * Pull the count of characters in the current edit field.
         * Plugins replacing the edit control may need to override this.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @return number of chars
         */
        CharacterCount: function (form) {
            return form.find('.notice_data-text:first').val().length;
        },

        /**
         * Called internally after the counter update blackout period expires;
         * runs another update to make sure we didn't miss anything.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @access private
         */
        ClearCounterBlackout: function (form) {
            // Allow keyup events to poke the counter again
            SN.C.I.CounterBlackout = false;
            // Check if the string changed since we last looked
            SN.U.Counter(form);
        },

        /**
         * Helper function to rewrite default HTTP form action URLs to HTTPS
         * so we can actually fetch them when on an SSL page in ssl=sometimes
         * mode.
         *
         * It would be better to output URLs that didn't hardcode protocol
         * and hostname in the first place...
         *
         * @param {String} url
         * @return string
         */
        RewriteAjaxAction: function (url) {
            // Quick hack: rewrite AJAX submits to HTTPS if they'd fail otherwise.
            if (document.location.protocol === 'https:' && url.substr(0, 5) === 'http:') {
                return url.replace(/^http:\/\/[^:\/]+/, 'https://' + document.location.host);
            }
            return url;
        },

        FormNoticeUniqueID: function (form) {
            var oldId = form.attr('id');
            var newId = 'form_notice_' + Math.floor(Math.random()*999999999);
            var attrs = ['name', 'for', 'id'];
            for (var key in attrs) {
                if (form.attr(attrs[key]) === undefined) {
                    continue;
                }
                form.attr(attrs[key], form.attr(attrs[key]).replace(oldId, newId));
            }
            for (var key in attrs) {
                form.find("[" + attrs[key] + "*='" + oldId + "']").each(function () {
                        if ($(this).attr(attrs[key]) === undefined) {
                            return; // since we're inside the each(function () { ... });
                        }
                        var newAttr = $(this).attr(attrs[key]).replace(oldId, newId);
                        $(this).attr(attrs[key], newAttr);
                    });
            }
        },

        /**
         * Grabs form data and submits it asynchronously, with 'ajax=1'
         * parameter added to the rest.
         *
         * If a successful response includes another form, that form
         * will be extracted and copied in, replacing the original form.
         * If there's no form, the first paragraph will be used.
         *
         * This will automatically be applied on the 'submit' event for
         * any form with the 'ajax' class.
         *
         * @fixme can sometimes explode confusingly if returnd data is bogus
         * @fixme error handling is pretty vague
         * @fixme can't submit file uploads
         *
         * @param {jQuery} form: jQuery object whose first element is a form
         * @param function onSuccess: something extra to do on success
         *
         * @access public
         */
        FormXHR: function (form, onSuccess) {
            $.ajax({
                type: 'POST',
                dataType: 'xml',
                url: SN.U.RewriteAjaxAction(form.attr('action')),
                data: form.serialize() + '&ajax=1',
                beforeSend: function (xhr) {
                    form
                        .addClass(SN.C.S.Processing)
                        .find('.submit')
                            .addClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, true);
                },
                error: function (xhr, textStatus, errorThrown) {
                    // If the server end reported an error from StatusNet,
                    // find it -- otherwise we'll see what was reported
                    // from the browser.
                    var errorReported = null;
                    if (xhr.responseXML) {
                        errorReported = $('#error', xhr.responseXML).text();
                    }
                    window.alert(errorReported || errorThrown || textStatus);

                    // Restore the form to original state.
                    // Hopefully. :D
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('.submit')
                            .removeClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, false);
                },
                success: function (data, textStatus) {
                    if ($('form', data)[0] !== undefined) {
                        var form_new = document._importNode($('form', data)[0], true);
                        form.replaceWith(form_new);
                        if (onSuccess) {
                            onSuccess();
                        }
                    } else if ($('p', data)[0] !== undefined) {
                        form.replaceWith(document._importNode($('p', data)[0], true));
                        if (onSuccess) {
                            onSuccess();
                        }
                    } else {
                        window.alert('Unknown error.');
                    }
                }
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for special-cased async submission of the
         * notice-posting form, including some pre-post validation.
         *
         * Unlike FormXHR() this does NOT submit the form immediately!
         * It sets up event handlers so that any method of submitting the
         * form (click on submit button, enter, submit() etc) will trigger
         * it properly.
         *
         * Also unlike FormXHR(), this system will use a hidden iframe
         * automatically to handle file uploads via <input type="file">
         * controls.
         *
         * @fixme tl;dr
         * @fixme vast swaths of duplicate code and really long variable names clutter this function up real bad
         * @fixme error handling is unreliable
         * @fixme cookieValue is a global variable, but probably shouldn't be
         * @fixme saving the location cache cookies should be split out
         * @fixme some error messages are hardcoded english: needs i18n
         *
         * @param {jQuery} form: jQuery object whose first element is a form
         *
         * @access public
         */
        FormNoticeXHR: function (form) {
            SN.C.I.NoticeDataGeo = {};
            form.append('<input type="hidden" name="ajax" value="1"/>');

            // Make sure we don't have a mixed HTTP/HTTPS submission...
            form.attr('action', SN.U.RewriteAjaxAction(form.attr('action')));

            /**
             * Hide the previous response feedback, if any.
             */
            var removeFeedback = function () {
                form.find('.form_response').remove();
            };

            form.ajaxForm({
                dataType: 'xml',
                timeout: SN.V.xhrTimeout,
                beforeSend: function (formData) {
                    if (form.find('.notice_data-text:first').val() == '') {
                        form.addClass(SN.C.S.Warning);
                        return false;
                    }
                    form
                        .addClass(SN.C.S.Processing)
                        .find('.submit')
                            .addClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, true);

                    SN.U.normalizeGeoData(form);

                    return true;
                },
                error: function (xhr, textStatus, errorThrown) {
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('.submit')
                            .removeClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, false);
                    removeFeedback();
                    if (textStatus == 'timeout') {
                        // @fixme i18n
                        SN.U.showFeedback(form, 'error', 'Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists.');
                    } else {
                        var response = SN.U.GetResponseXML(xhr);
                        if ($('.' + SN.C.S.Error, response).length > 0) {
                            form.append(document._importNode($('.' + SN.C.S.Error, response)[0], true));
                        } else {
                            if (parseInt(xhr.status) === 0 || $.inArray(parseInt(xhr.status), SN.C.I.HTTP20x30x) >= 0) {
                                form
                                    .resetForm()
                                    .find('.attach-status').remove();
                                SN.U.FormNoticeEnhancements(form);
                            } else {
                                // @fixme i18n
                                SN.U.showFeedback(form, 'error', '(Sorry! We had trouble sending your notice (' + xhr.status + ' ' + xhr.statusText + '). Please report the problem to the site administrator if this happens again.');
                            }
                        }
                    }
                },
                success: function (data, textStatus) {
                    removeFeedback();
                    var errorResult = $('#' + SN.C.S.Error, data);
                    if (errorResult.length > 0) {
                        SN.U.showFeedback(form, 'error', errorResult.text());
                    } else {
                        SN.E.ajaxNoticePosted(form, data, textStatus);
                    }
                },
                complete: function (xhr, textStatus) {
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('.submit')
                            .prop(SN.C.S.Disabled, false)
                            .removeClass(SN.C.S.Disabled);

                    form.find('[name=lat]').val(SN.C.I.NoticeDataGeo.NLat);
                    form.find('[name=lon]').val(SN.C.I.NoticeDataGeo.NLon);
                    form.find('[name=location_ns]').val(SN.C.I.NoticeDataGeo.NLNS);
                    form.find('[name=location_id]').val(SN.C.I.NoticeDataGeo.NLID);
                    form.find('[name=notice_data-geo]').prop('checked', SN.C.I.NoticeDataGeo.NDG);
                }
            });
        },

        FormProfileSearchXHR: function (form) {
            $.ajax({
                type: 'POST',
                dataType: 'xml',
                url: form.attr('action'),
                data: form.serialize() + '&ajax=1',
                beforeSend: function (xhr) {
                    form
                        .addClass(SN.C.S.Processing)
                        .find('.submit')
                            .addClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, true);
                },
                error: function (xhr, textStatus, errorThrown) {
                    window.alert(errorThrown || textStatus);
                },
                success: function (data, textStatus) {
                    var results_placeholder = $('#profile_search_results');
                    if ($('ul', data)[0] !== undefined) {
                        var list = document._importNode($('ul', data)[0], true);
                        results_placeholder.replaceWith(list);
                    } else {
                        var _error = $('<li/>').append(document._importNode($('p', data)[0], true));
                        results_placeholder.html(_error);
                    }
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('.submit')
                            .removeClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, false);
                }
            });
        },

        FormPeopletagsXHR: function (form) {
            $.ajax({
                type: 'POST',
                dataType: 'xml',
                url: form.attr('action'),
                data: form.serialize() + '&ajax=1',
                beforeSend: function (xhr) {
                    form.find('.submit')
                            .addClass(SN.C.S.Processing)
                            .addClass(SN.C.S.Disabled)
                            .prop(SN.C.S.Disabled, true);
                },
                error: function (xhr, textStatus, errorThrown) {
                    window.alert(errorThrown || textStatus);
                },
                success: function (data, textStatus) {
                    var results_placeholder = form.parents('.entity_tags');
                    if ($('.entity_tags', data)[0] !== undefined) {
                        var tags = document._importNode($('.entity_tags', data)[0], true);
                        $(tags).find('.editable').append($('<button class="peopletags_edit_button"/>'));
                        results_placeholder.replaceWith(tags);
                    } else {
                        results_placeholder.find('p').remove();
                        results_placeholder.append(document._importNode($('p', data)[0], true));
                        form.removeClass(SN.C.S.Processing)
                            .find('.submit')
                                .removeClass(SN.C.S.Disabled)
                                .prop(SN.C.S.Disabled, false);
                    }
                }
            });
        },

        normalizeGeoData: function (form) {
            SN.C.I.NoticeDataGeo.NLat = form.find('[name=lat]').val();
            SN.C.I.NoticeDataGeo.NLon = form.find('[name=lon]').val();
            SN.C.I.NoticeDataGeo.NLNS = form.find('[name=location_ns]').val();
            SN.C.I.NoticeDataGeo.NLID = form.find('[name=location_id]').val();
            SN.C.I.NoticeDataGeo.NDG = form.find('[name=notice_data-geo]').prop('checked'); // @fixme (does this still need to be fixed somehow?)

            var cookieValue = $.cookie(SN.C.S.NoticeDataGeoCookie);

            if (cookieValue !== undefined && cookieValue != 'disabled') {
                cookieValue = JSON.parse(cookieValue);
                SN.C.I.NoticeDataGeo.NLat = form.find('[name=lat]').val(cookieValue.NLat).val();
                SN.C.I.NoticeDataGeo.NLon = form.find('[name=lon]').val(cookieValue.NLon).val();
                if (cookieValue.NLNS) {
                    SN.C.I.NoticeDataGeo.NLNS = form.find('[name=location_ns]').val(cookieValue.NLNS).val();
                    SN.C.I.NoticeDataGeo.NLID = form.find('[name=location_id]').val(cookieValue.NLID).val();
                } else {
                    form.find('[name=location_ns]').val('');
                    form.find('[name=location_id]').val('');
                }
            }
            if (cookieValue == 'disabled') {
                SN.C.I.NoticeDataGeo.NDG = form.find('[name=notice_data-geo]').prop('checked', false).prop('checked');
            } else {
                SN.C.I.NoticeDataGeo.NDG = form.find('[name=notice_data-geo]').prop('checked', true).prop('checked');
            }

        },

        /**
         * Fetch an XML DOM from an XHR's response data.
         *
         * Works around unavailable responseXML when document.domain
         * has been modified by Meteor or other tools, in some but not
         * all browsers.
         *
         * @param {XMLHTTPRequest} xhr
         * @return DOMDocument
         */
        GetResponseXML: function (xhr) {
            try {
                return xhr.responseXML;
            } catch (e) {
                return (new DOMParser()).parseFromString(xhr.responseText, "text/xml");
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on all visible notice's option <a> elements
         * with the "popup" class so they behave as expected with AJAX.
         *
         * (without javascript the link goes to a page that expects you to verify
         * the action through a form)
         *
         * @access private
         */
        NoticeOptionsAjax: function () {
            $(document).on('click', '.notice-options > a.popup', function (e) {
                e.preventDefault();
                var noticeEl = $(this).closest('.notice');
                $.ajax({
                    url: $(this).attr('href'),
                    data: {ajax: 1},
                    success: function (data, textStatus, xhr) {
                        SN.U.NoticeOptionPopup(data, noticeEl);
                    },
                });
                return false;
            });
        },

        NoticeOptionPopup: function (data, noticeEl) {
            title = $('head > title', data).text();
            body = $('body', data).html();
            dialog = $(body).dialog({
                    height: "auto",
                    width: "auto",
                    modal: true,
                    resizable: true,
                    title: title,
                });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on all visible notice's reply buttons to
         * tweak the new-notice form with needed variables and focus it
         * when pushed.
         *
         * (This replaces the default reply button behavior to submit
         * directly to a form which comes back with a specialized page
         * with the form data prefilled.)
         *
         * @access private
         */
        NoticeReply: function () {
            $(document).on('click', '#content .notice_reply', function (e) {
                e.preventDefault();
                var notice = $(this).closest('li.notice');
                SN.U.NoticeInlineReplyTrigger(notice);
                return false;
            });
        },

        /**
         * Stub -- kept for compat with plugins for now.
         * @access private
         */
        NoticeReplyTo: function (notice) {
        },

        /**
         * Open up a notice's inline reply box.
         *
         * @param {jQuery} notice: jQuery object containing one notice
         * @param {String} initialText
         */
        NoticeInlineReplyTrigger: function (notice, initialText) {
            // Find the notice we're replying to...
            var id = $($('.notice_id', notice)[0]).text();
            var replyForm;
            var parentNotice = notice;
            var stripForm = true; // strip a couple things out of reply forms that are inline

            var list = notice.find('.threaded-replies');
            if (list.length == 0) {
                list = notice.closest('.threaded-replies');
            }
            if (list.length == 0) {
                list = $('<ul class="notices threaded-replies xoxo"></ul>');
                notice.append(list);
                list = notice.find('.threaded-replies');
            }

            var nextStep = function () {
                // Override...?
                replyForm.find('input[name=inreplyto]').val(id);
                if (stripForm) {
                    // Don't do this for old-school reply form, as they don't come back!
                    replyForm.find('#notice_to').prop('disabled', true).hide();
                    replyForm.find('#notice_private').prop('disabled', true).hide();
                    replyForm.find('label[for=notice_to]').hide();
                    replyForm.find('label[for=notice_private]').hide();
                }
                replyItem.show();

                // Set focus...
                var text = replyForm.find('textarea');
                if (text.length == 0) {
                    throw "No textarea";
                }
                var replyto = '';
                if (initialText) {
                    replyto = initialText + ' ';
                }
                text.val(replyto + text.val().replace(new RegExp(replyto, 'i'), ''));
                text.data('initialText', $.trim(initialText));
                text.focus();
                if (text[0].setSelectionRange) {
                    var len = text.val().length;
                    text[0].setSelectionRange(len, len);
                }
            };

            // Create the reply form entry
            var replyItem = $('li.notice-reply', list);
            if (replyItem.length == 0) {
                replyItem = $('<li class="notice-reply"></li>');
            }
            replyForm = replyItem.children('form');
            if (replyForm.length == 0) {
                // Let's try another trick to avoid fetching by URL
                var noticeForm = $('#input_form_status > form');
                if (noticeForm.length == 0) {
                    // No notice form found on the page, so let's just
                    // fetch a fresh copy of the notice form over AJAX.
                    $.ajax({
                        url: SN.V.urlNewNotice,
                        data: {ajax: 1, inreplyto: id},
                        success: function (data, textStatus, xhr) {
                            var formEl = document._importNode($('form', data)[0], true);
                            replyForm = $(formEl);
                            replyItem.append(replyForm);
                            list.append(replyItem);

                            SN.Init.NoticeFormSetup(replyForm);
                            nextStep();
                        },
                    });
                    // We do everything relevant in 'success' above
                    return;
                }
                replyForm = noticeForm.clone();
                SN.Init.NoticeFormSetup(replyForm);
                replyItem.append(replyForm);
                list.append(replyItem);
            }
            // replyForm is set, we're not fetching by URL...
            // Next setp is to configure in-reply-to etc.
            nextStep();
        },

        /**
         * Setup function -- DOES NOT apply immediately.
         *
         * Uses 'on' rather than 'live' or 'bind', so applies to future as well as present items.
         */
        NoticeInlineReplySetup: function () {
            // Expand conversation links
            $(document).on('click', 'li.notice-reply-comments a', function () {
                    var url = $(this).attr('href');
                    var area = $(this).closest('.threaded-replies');
                    $.ajax({
                        url: url,
                        data: {ajax: 1},
                        success: function (data, textStatus, xhr) {
                            var replies = $('.threaded-replies', data);
                            if (replies.length) {
                                area.replaceWith(document._importNode(replies[0], true));
                            }
                        },
                    });
                    return false;
                });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for repeat forms to toss up a confirmation
         * popout before submitting.
         *
         * Uses 'on' rather than 'live' or 'bind', so applies to future as well as present items.
         *
         */
        NoticeRepeat: function () {
            $('body').on('click', '.form_repeat', function (e) {
                e.preventDefault();

                SN.U.NoticeRepeatConfirmation($(this));
                return false;
            });
        },

        /**
         * Shows a confirmation dialog box variant of the repeat button form.
         * This seems to use a technique where the repeat form contains
         * _both_ a standalone button _and_ text and buttons for a dialog.
         * The dialog will close after its copy of the form is submitted,
         * or if you click its 'close' button.
         *
         * The dialog is created by duplicating the original form and changing
         * its style; while clever, this is hard to generalize and probably
         * duplicates a lot of unnecessary HTML output.
         *
         * @fixme create confirmation dialogs through a generalized interface
         * that can be reused instead of hardcoded text and styles.
         *
         * @param {jQuery} form
         */
        NoticeRepeatConfirmation: function (form) {
            var submit_i = form.find('.submit');

            var submit = submit_i.clone();
            submit
                .addClass('submit_dialogbox')
                .removeClass('submit');
            form.append(submit);
            submit.on('click', function () { SN.U.FormXHR(form); return false; });

            submit_i.hide();

            form
                .addClass('dialogbox')
                .append('<button class="close" title="' + SN.msg('popup_close_button') + '">&#215;</button>')
                .closest('.notice-options')
                    .addClass('opaque');

            form.find('button.close').click(function () {
                $(this).remove();

                form
                    .removeClass('dialogbox')
                    .closest('.notice-options')
                        .removeClass('opaque');

                form.find('.submit_dialogbox').remove();
                form.find('.submit').show();

                return false;
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Goes through all notices currently displayed and sets up attachment
         * handling if needed.
         */
        NoticeAttachments: function () {
            $('.notice a.attachment').each(function () {
                SN.U.NoticeWithAttachment($(this).closest('.notice'));
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up special attachment link handling if needed. Currently this
         * consists only of making the "more" button used for OStatus message
         * cropping turn into an auto-expansion button that loads the full
         * text from an attachment file.
         *
         * @param {jQuery} notice
         */
        NoticeWithAttachment: function (notice) {
            if (notice.find('.attachment').length === 0) {
                return;
            }

			$(document).on('click','.attachment.more',function () {
				var m = $(this);
				m.addClass(SN.C.S.Processing);
				$.get(m.attr('href'), {ajax: 1}, function (data) {
					m.parent('.e-content').html($(data).find('#attachment_view .e-content').html());
				});

				return false;
			});

        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for the file-attachment widget in the
         * new notice form. When a file is selected, a box will be added
         * below the text input showing the filename and, if supported
         * by the browser, a thumbnail preview.
         *
         * This preview box will also allow removing the attachment
         * prior to posting.
         *
         * @param {jQuery} form
         */
        NoticeDataAttach: function (form) {
            var i;
            var NDA = form.find('input[type=file]');
            NDA.change(function (event) {
                form.find('.attach-status').remove();

                if (typeof this.files === "object") {
                    var attachStatus = $('<ul class="attach-status ' + SN.C.S.Success + '"></ul>');
                    form.append(attachStatus);
                    // Some newer browsers will let us fetch the files for preview.
                    for (i = 0; i < this.files.length; i++) {
                        SN.U.PreviewAttach(form, this.files[i]);
                    }
                } else {
                    var filename = $(this).val();
                    if (!filename) {
                        // No file -- we've been tricked!
                        return false;
                    }

                    var attachStatus = $('<div class="attach-status ' + SN.C.S.Success + '"><code></code> <button class="close">&#215;</button></div>');
                    attachStatus.find('code').text(filename);
                    attachStatus.find('button').click(function () {
                        attachStatus.remove();
                        NDA.val('');

                        return false;
                    });
                    form.append(attachStatus);
                }
            });
        },

        /**
         * Get PHP's MAX_FILE_SIZE setting for this form;
         * used to apply client-side file size limit checks.
         *
         * @param {jQuery} form
         * @return int max size in bytes; 0 or negative means no limit
         */
        maxFileSize: function (form) {
            var max = $(form).find('input[name=MAX_FILE_SIZE]').attr('value');
            if (max) {
                return parseInt(max);
            }
            return 0;
        },

        /**
         * For browsers with FileAPI support: make a thumbnail if possible,
         * and append it into the attachment display widget.
         *
         * Known good:
         * - Firefox 3.6.6, 4.0b7
         * - Chrome 8.0.552.210
         *
         * Known ok metadata, can't get contents:
         * - Safari 5.0.2
         *
         * Known fail:
         * - Opera 10.63, 11 beta (no input.files interface)
         *
         * @param {jQuery} form
         * @param {File} file
         *
         * @todo use configured thumbnail size
         * @todo detect pixel size?
         * @todo should we render a thumbnail to a canvas and then use the smaller image?
         */
        PreviewAttach: function (form, file) {
            var tooltip = file.type + ' ' + Math.round(file.size / 1024) + 'KB';
            var preview = true;

            var blobAsDataURL;
            if (window.createObjectURL !== undefined) {
                /**
                 * createObjectURL lets us reference the file directly from an <img>
                 * This produces a compact URL with an opaque reference to the file,
                 * which we can reference immediately.
                 *
                 * - Firefox 3.6.6: no
                 * - Firefox 4.0b7: no
                 * - Safari 5.0.2: no
                 * - Chrome 8.0.552.210: works!
                 */
                blobAsDataURL = function (blob, callback) {
                    callback(window.createObjectURL(blob));
                };
            } else if (window.FileReader !== undefined) {
                /**
                 * FileAPI's FileReader can build a data URL from a blob's contents,
                 * but it must read the file and build it asynchronously. This means
                 * we'll be passing a giant data URL around, which may be inefficient.
                 *
                 * - Firefox 3.6.6: works!
                 * - Firefox 4.0b7: works!
                 * - Safari 5.0.2: no
                 * - Chrome 8.0.552.210: works!
                 */
                blobAsDataURL = function (blob, callback) {
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        callback(reader.result);
                    };
                    reader.readAsDataURL(blob);
                };
            } else {
                preview = false;
            }

            var imageTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml',
                              'image/bmp', 'image/webp', 'image/vnd.microsoft.icon'];
            if ($.inArray(file.type, imageTypes) == -1) {
                // We probably don't know how to show the file.
                preview = false;
            }

            var maxSize = 8 * 1024 * 1024;
            if (file.size > maxSize) {
                // Don't kill the browser trying to load some giant image.
                preview = false;
            }

            var fileentry = $('<li>')
                .attr('class', 'attachment')
                .attr('style', 'text-align: center');
            if (preview) {
                blobAsDataURL(file, function (url) {
                    var img = $('<img>')
                        .attr('title', tooltip)
                        .attr('alt', tooltip)
                        .attr('src', url)
                        .attr('style', 'height: 120px');
                    fileentry.append(img);
                    fileentry.append($('<br><code>' + file.name + '</code>'));
                    form.find('.attach-status').append(fileentry);
                });
            } else {
                fileentry.append($('<code>' + file.type + '</code>'));
                fileentry.append($('<br><code>' + file.name + '</code>'));
                form.find('.attach-status').append(fileentry);
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Initializes state for the location-lookup features in the
         * new-notice form. Seems to set up some event handlers for
         * triggering lookups and using the new values.
         *
         * @param {jQuery} form
         *
         * @fixme tl;dr
         * @fixme there's not good visual state update here, so users have a
         *        hard time figuring out if it's working or fixing if it's wrong.
         *
         */
        NoticeLocationAttach: function (form) {
            // @fixme this should not be tied to the main notice form, as there may be multiple notice forms...
            var NLat = form.find('[name=lat]');
            var NLon = form.find('[name=lon]');
            var NLNS = form.find('[name=location_ns]').val();
            var NLID = form.find('[name=location_id]').val();
            var NLN = ''; // @fixme
            var NDGe = form.find('[name=notice_data-geo]');
            var check = form.find('[name=notice_data-geo]');
            var label = form.find('label.notice_data-geo');

            function removeNoticeDataGeo(error) {
                label
                    .attr('title', $.trim(label.text()))
                    .removeClass('checked');

                form.find('[name=lat]').val('');
                form.find('[name=lon]').val('');
                form.find('[name=location_ns]').val('');
                form.find('[name=location_id]').val('');
                form.find('[name=notice_data-geo]').prop('checked', false);

                $.cookie(SN.C.S.NoticeDataGeoCookie, 'disabled', { path: '/' });

                if (error) {
                    form.find('.geo_status_wrapper').removeClass('success').addClass('error');
                    form.find('.geo_status_wrapper .geo_status').text(error);
                } else {
                    form.find('.geo_status_wrapper').remove();
                }
            }

            function getJSONgeocodeURL(geocodeURL, data) {
                SN.U.NoticeGeoStatus(form, 'Looking up place name...');
                $.getJSON(geocodeURL, data, function (location) {
                    var lns, lid, NLN_text;

                    if (location.location_ns !== undefined) {
                        form.find('[name=location_ns]').val(location.location_ns);
                        lns = location.location_ns;
                    }

                    if (location.location_id !== undefined) {
                        form.find('[name=location_id]').val(location.location_id);
                        lid = location.location_id;
                    }

                    if (location.name === undefined) {
                        NLN_text = data.lat + ';' + data.lon;
                    } else {
                        NLN_text = location.name;
                    }

                    SN.U.NoticeGeoStatus(form, NLN_text, data.lat, data.lon, location.url);
                    label
                        .attr('title', NoticeDataGeo_text.ShareDisable + ' (' + NLN_text + ')');

                    form.find('[name=lat]').val(data.lat);
                    form.find('[name=lon]').val(data.lon);
                    form.find('[name=location_ns]').val(lns);
                    form.find('[name=location_id]').val(lid);
                    form.find('[name=notice_data-geo]').prop('checked', true);

                    var cookieValue = {
                        NLat: data.lat,
                        NLon: data.lon,
                        NLNS: lns,
                        NLID: lid,
                        NLN: NLN_text,
                        NLNU: location.url,
                        NDG: true
                    };

                    $.cookie(SN.C.S.NoticeDataGeoCookie, JSON.stringify(cookieValue), { path: '/' });
                });
            }

            if (check.length > 0) {
                if ($.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                    check.prop('checked', false);
                } else {
                    check.prop('checked', true);
                }

                var NGW = form.find('.notice_data-geo_wrap');
                var geocodeURL = NGW.attr('data-api');

                label.attr('title', label.text());

                check.change(function () {
                    if (check.prop('checked') === true || $.cookie(SN.C.S.NoticeDataGeoCookie) === undefined) {
                        label
                            .attr('title', NoticeDataGeo_text.ShareDisable)
                            .addClass('checked');

                        if ($.cookie(SN.C.S.NoticeDataGeoCookie) === undefined || $.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                            if (navigator.geolocation) {
                                SN.U.NoticeGeoStatus(form, 'Requesting location from browser...');
                                navigator.geolocation.getCurrentPosition(
                                    function (position) {
                                        form.find('[name=lat]').val(position.coords.latitude);
                                        form.find('[name=lon]').val(position.coords.longitude);

                                        var data = {
                                            lat: position.coords.latitude,
                                            lon: position.coords.longitude,
                                            token: $('#token').val()
                                        };

                                        getJSONgeocodeURL(geocodeURL, data);
                                    },

                                    function (error) {
                                        switch(error.code) {
                                            case error.PERMISSION_DENIED:
                                                removeNoticeDataGeo('Location permission denied.');
                                                break;
                                            case error.TIMEOUT:
                                                //$('#' + SN.C.S.NoticeDataGeo).prop('checked', false);
                                                removeNoticeDataGeo('Location lookup timeout.');
                                                break;
                                        }
                                    },

                                    {
                                        timeout: 10000
                                    }
                                );
                            } else {
                                if (NLat.length > 0 && NLon.length > 0) {
                                    var data = {
                                        lat: NLat,
                                        lon: NLon,
                                        token: $('#token').val()
                                    };

                                    getJSONgeocodeURL(geocodeURL, data);
                                } else {
                                    removeNoticeDataGeo();
                                    check.remove();
                                    label.remove();
                                }
                            }
                        } else {
                            try {
                                var cookieValue = JSON.parse($.cookie(SN.C.S.NoticeDataGeoCookie));

                                form.find('[name=lat]').val(cookieValue.NLat);
                                form.find('[name=lon]').val(cookieValue.NLon);
                                form.find('[name=location_ns]').val(cookieValue.NLNS);
                                form.find('[name=location_id]').val(cookieValue.NLID);
                                form.find('[name=notice_data-geo]').prop('checked', cookieValue.NDG);

                               SN.U.NoticeGeoStatus(form, cookieValue.NLN, cookieValue.NLat, cookieValue.NLon, cookieValue.NLNU);
                                label
                                    .attr('title', NoticeDataGeo_text.ShareDisable + ' (' + cookieValue.NLN + ')')
                                    .addClass('checked');
                            } catch (e) {
                                console.log('Parsing error:', e);
                            }
                        }
                    } else {
                        removeNoticeDataGeo();
                    }
                }).change();
            }
        },

        /**
         * Create or update a geolocation status widget in this notice posting form.
         *
         * @param {jQuery} form
         * @param {String} status
         * @param {String} lat (optional)
         * @param {String} lon (optional)
         * @param {String} url (optional)
         */
        NoticeGeoStatus: function (form, status, lat, lon, url)
        {
            var wrapper = form.find('.geo_status_wrapper');
            if (wrapper.length == 0) {
                wrapper = $('<div class="' + SN.C.S.Success + ' geo_status_wrapper"><button class="close" style="float:right">&#215;</button><div class="geo_status"></div></div>');
                wrapper.find('button.close').click(function () {
                    form.find('[name=notice_data-geo]').prop('checked', false).change();
                    return false;
                });
                form.append(wrapper);
            }
            var label;
            if (url) {
                label = $('<a></a>').attr('href', url);
            } else {
                label = $('<span></span>');
            }
            label.text(status);
            if (lat || lon) {
                var latlon = lat + ';' + lon;
                label.attr('title', latlon);
                if (!status) {
                    label.text(latlon)
                }
            }
            wrapper.find('.geo_status').empty().append(label);
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Initializes event handlers for the "Send direct message" link on
         * profile pages, setting it up to display a dialog box when clicked.
         *
         * Unlike the repeat confirmation form, this appears to fetch
         * the form _from the original link target_, so the form itself
         * doesn't need to be in the current document.
         *
         * @fixme breaks ability to open link in new window?
         */
        NewDirectMessage: function () {
            NDM = $('.entity_send-a-message a');
            NDM.attr({'href': NDM.attr('href') + '&ajax=1'});
            NDM.on('click', function () {
                var NDMF = $('.entity_send-a-message form');
                if (NDMF.length === 0) {
                    $(this).addClass(SN.C.S.Processing);
                    $.get(NDM.attr('href'), null, function (data) {
                        $('.entity_send-a-message').append(document._importNode($('form', data)[0], true));
                        NDMF = $('.entity_send-a-message .form_notice');
                        SN.U.FormNoticeXHR(NDMF);
                        SN.U.FormNoticeEnhancements(NDMF);
                        NDMF.append('<button class="close">&#215;</button>');
                        $('.entity_send-a-message button').click(function () {
                            NDMF.hide();
                            return false;
                        });
                        NDM.removeClass(SN.C.S.Processing);
                    });
                } else {
                    NDMF.show();
                    $('.entity_send-a-message textarea').focus();
                }
                return false;
            });
        },

        /**
         * Return a date object with the current local time on the
         * given year, month, and day.
         *
         * @param {number} year: 4-digit year
         * @param {number} month: 0 == January
         * @param {number} day: 1 == 1
         * @return {Date}
         */
        GetFullYear: function (year, month, day) {
            var date = new Date();
            date.setFullYear(year, month, day);

            return date;
        },

        /**
         * Check if the current page is a timeline where the current user's
         * posts should be displayed immediately on success.
         *
         * @fixme this should be done in a saner way, with machine-readable
         * info about what page we're looking at.
         *
         * @param {DOMElement} notice: HTML chunk with formatted notice
         * @return boolean
         */
        belongsOnTimeline: function (notice) {
            var action = $("body").attr('id');
            if (action == 'public') {
                return true;
            }

            var profileLink = $('#nav_profile a').attr('href');
            if (profileLink) {
                var authorUrl = $(notice).find('.h-card.p-author').attr('href');
                if (authorUrl == profileLink) {
                    if (action == 'all' || action == 'showstream') {
                        // Posts always show on your own friends and profile streams.
                        return true;
                    }
                }
            }

            // @fixme tag, group, reply timelines should be feasible as well.
            // Mismatch between id-based and name-based user/group links currently complicates
            // the lookup, since all our inline mentions contain the absolute links but the
            // UI links currently on the page use malleable names.
            
            return false;
        },

        /**
         * Switch to another active input sub-form.
         * This will hide the current form (if any), show the new one, and
         * update the input type tab selection state.
         *
         * @param {String} tag
         */
        switchInputFormTab: function (tag, setFocus) {
            if (typeof setFocus === 'undefined') { setFocus = true; }
            // The one that's current isn't current anymore
            $('.input_form_nav_tab.current').removeClass('current');
            if (tag != null) {
                $('#input_form_nav_' + tag).addClass('current');
            }

            // Don't remove 'current' if we also have the "nonav" class.
            // An example would be the message input form. removing
            // 'current' will cause the form to vanish from the page.
            var nonav = $('.input_form.current.nonav');
            if (nonav.length > 0) {
                return;
            }

            $('.input_form.current').removeClass('current');
            if (tag == null) {
                // we're done here, no new inputform to focus on
                return false;
            }

            var noticeForm = $('#input_form_' + tag)
                    .addClass('current')
                    .find('.ajax-notice').each(function () {
                        var form = $(this);
                        SN.Init.NoticeFormSetup(form);
                    });
            if (setFocus) {
                noticeForm.find('.notice_data-text').focus();
            }

            return false;
        },

        showMoreMenuItems: function (menuid) {
            $('#' + menuid + ' .more_link').remove();
            var selector = '#' + menuid + ' .extended_menu';
            var extended = $(selector);
            extended.removeClass('extended_menu');
            return void(0);
        },

        /**
         * Show a response feedback bit under a form.
         *
         * @param {Element} form: the new-notice form usually
         * @param {String}  cls: CSS class name to use ('error' or 'success')
         * @param {String}  text
         * @access public
         */
        showFeedback: function (form, cls, text) {
            form.append(
                $('<p class="form_response"></p>')
                    .addClass(cls)
                    .text(text)
            );
        },

        addCallback: function (ename, callback) {
            // initialize to array if it's undefined
            if (typeof SN._callbacks[ename] === 'undefined') {
                SN._callbacks[ename] = [];
            }
            SN._callbacks[ename].push(callback);
        },

        runCallbacks: function (ename, data) {
            if (typeof SN._callbacks[ename] === 'undefined') {
                return;
            }
            for (cbname in SN._callbacks[ename]) {
                SN._callbacks[ename][cbname](data);
            }
        }
    },

    E: {    /* Events */
        /* SN.E.ajaxNoticePosted, called when a notice has been posted successfully via an AJAX form
            @param  form        the originating form element
            @param  data        data from success() callback
            @param  textStatus  textStatus from success() callback
        */
        ajaxNoticePosted: function (form, data, textStatus) {
            var commandResult = $('#' + SN.C.S.CommandResult, data);
            if (commandResult.length > 0) {
                SN.U.showFeedback(form, 'success', commandResult.text());
            } else {
                // New notice post was successful. If on our timeline, show it!
                var notice = document._importNode($('li', data)[0], true);
                var notices = $('#notices_primary .notices:first');
                var replyItem = form.closest('li.notice-reply');

                if (replyItem.length > 0) {
                    // If this is an inline reply, remove the form...
                    var list = form.closest('.threaded-replies');

                    var id = $(notice).attr('id');
                    if ($('#' + id).length == 0) {
                        $(notice).insertBefore(replyItem);
                    } // else Realtime came through before us...

                    replyItem.remove();

                } else if (notices.length > 0 && SN.U.belongsOnTimeline(notice)) {
                    // Not a reply. If on our timeline, show it at the top!

                    if ($('#' + notice.id).length === 0) {
                        var notice_irt_value = form.find('[name=inreplyto]').val();
                        var notice_irt = '#notices_primary #notice-' + notice_irt_value;
                        if ($('body')[0].id == 'conversation') {
                            if (notice_irt_value.length > 0 && $(notice_irt + ' .notices').length < 1) {
                                $(notice_irt).append('<ul class="notices"></ul>');
                            }
                            $($(notice_irt + ' .notices')[0]).append(notice);
                        } else {
                            notices.prepend(notice);
                        }
                        $('#' + notice.id)
                            .css({display: 'none'})
                            .fadeIn(2500);
                        SN.U.NoticeWithAttachment($('#' + notice.id));
                        SN.U.switchInputFormTab(null);
                    }
                } else {
                    // Not on a timeline that this belongs on?
                    // Just show a success message.
                    // @fixme inline
                    SN.U.showFeedback(form, 'success', $('title', data).text());
                }
            }
            form.resetForm();
            form.find('[name=inreplyto]').val('');
            form.find('.attach-status').remove();
            SN.U.FormNoticeEnhancements(form);

            SN.U.runCallbacks('notice_posted', {"notice": notice});
        }, 
    },


    Init: {
        /**
         * If user is logged in, run setup code for the new notice form:
         *
         *  - char counter
         *  - AJAX submission
         *  - location events
         *  - file upload events
         */
        NoticeForm: function () {
            if ($('body.user_in').length > 0) {
                // SN.Init.NoticeFormSetup() will get run
                // when forms get displayed for the first time...

                // Initialize the input form field
                $('#input_form_nav .input_form_nav_tab.current').each(function () {
                    current_tab_id = $(this).attr('id').substring('input_form_nav_'.length);
                    SN.U.switchInputFormTab(current_tab_id, false);
                });

                // Make inline reply forms self-close when clicking out.
                $('body').on('click', function (e) {
                    var openReplies = $('li.notice-reply');
                    if (openReplies.length > 0) {
                        var target = $(e.target);
                        openReplies.each(function () {
                            // Did we click outside this one?
                            var replyItem = $(this);
                            if (replyItem.has(e.target).length == 0) {
                                var textarea = replyItem.find('.notice_data-text:first');
                                var cur = $.trim(textarea.val());
                                // Only close if there's been no edit.
                                if (cur == '' || cur == textarea.data('initialText')) {
                                    var parentNotice = replyItem.closest('li.notice');
                                    replyItem.hide();
                                    parentNotice.find('li.notice-reply-placeholder').show();
                                }
                            }
                        });
                    }
                });
            }
        },

        /**
         * Encapsulate notice form setup for a single form.
         * Plugins can add extra setup by monkeypatching this
         * function.
         *
         * @param {jQuery} form
         */
        NoticeFormSetup: function (form) {
            if (form.data('NoticeFormSetup')) {
                return false;
            }
            SN.U.NoticeLocationAttach(form);
            SN.U.FormNoticeUniqueID(form);
            SN.U.FormNoticeXHR(form);
            SN.U.FormNoticeEnhancements(form);
            SN.U.NoticeDataAttach(form);
            form.data('NoticeFormSetup', true);
        },

        /**
         * Run setup code for notice timeline views items:
         *
         * - AJAX submission for fave/repeat/reply (if logged in)
         * - Attachment link extras ('more' links)
         */
        Notices: function () {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeRepeat();
                SN.U.NoticeReply();
                SN.U.NoticeInlineReplySetup();
                SN.U.NoticeOptionsAjax();
            }

            SN.U.NoticeAttachments();
        },

        /**
         * Run setup code for user & group profile page header area if logged in:
         *
         * - AJAX submission for sub/unsub/join/leave/nudge
         * - AJAX form popup for direct-message
         */
        EntityActions: function () {
            if ($('body.user_in').length > 0) {
                $(document).on('click', '.form_user_subscribe', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_user_unsubscribe', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_group_join', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_group_leave', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_user_nudge', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_peopletag_subscribe', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_peopletag_unsubscribe', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_user_add_peopletag', function () { SN.U.FormXHR($(this)); return false; });
                $(document).on('click', '.form_user_remove_peopletag', function () { SN.U.FormXHR($(this)); return false; });

                SN.U.NewDirectMessage();
            }
        },

        ProfileSearch: function () {
            if ($('body.user_in').length > 0) {
                $(document).on('click', '.form_peopletag_edit_user_search input.submit', function () {
                    SN.U.FormProfileSearchXHR($(this).parents('form')); return false;
                });
            }
        },

        /**
         * Run setup for the ajax people tags editor
         *
         * - show edit button
         * - set event handle for click on edit button
         *   - loads people tag autocompletion data if not already present
         *     or if it is stale.
         *
         */
        PeopleTags: function () {
            $('.user_profile_tags .editable').append($('<button class="peopletags_edit_button"/>'));

            $(document).on('click', '.peopletags_edit_button', function () {
                var form = $(this).parents('dd').eq(0).find('form');
                // We can buy time from the above animation

                $.ajax({
                    url: _peopletagAC,
                    dataType: 'json',
                    data: {token: $('#token').val()},
                    ifModified: true,
                    success: function (data) {
                        // item.label is used to match
                        for (i=0; i < data.length; i++) {
                            data[i].label = data[i].tag;
                        }

                        SN.C.PtagACData = data;
                    }
                });

                $(this).parents('ul').eq(0).fadeOut(200, function () {form.fadeIn(200).find('input#tags')});
            });

            $(document).on('click', '.user_profile_tags form .submit', function () {
                SN.U.FormPeopletagsXHR($(this).parents('form')); return false;
            });
        },

        /**
         * Set up any generic 'ajax' form so it submits via AJAX with auto-replacement.
         */
        AjaxForms: function () {
            $(document).on('submit', 'form.ajax', function () {
                SN.U.FormXHR($(this));
                return false;
            });
            $(document).on('click', 'form.ajax input[type=submit]', function () {
                // Some forms rely on knowing which submit button was clicked.
                // Save a hidden input field which'll be picked up during AJAX
                // submit...
                var button = $(this);
                var form = button.closest('form');
                form.find('.hidden-submit-button').remove();
                $('<input class="hidden-submit-button" type="hidden" />')
                    .attr('name', button.attr('name'))
                    .val(button.val())
                    .appendTo(form);
            });
        },

        /**
         * Add logic to any file upload forms to handle file size limits,
         * on browsers that support basic FileAPI.
         */
        UploadForms: function () {
            $('input[type=file]').change(function (event) {
                if (typeof this.files === "object" && this.files.length > 0) {
                    var size = 0;
                    for (var i = 0; i < this.files.length; i++) {
                        size += this.files[i].size;
                    }

                    var max = SN.U.maxFileSize($(this.form));
                    if (max > 0 && size > max) {
                        var msg = 'File too large: maximum upload size is %d bytes.';
                        alert(msg.replace('%d', max));

                        // Clear the files.
                        $(this).val('');
                        event.preventDefault();
                        return false;
                    }
                }
            });
        },

        CheckBoxes: function () {
            $("span[class='checkbox-wrapper']").addClass("unchecked");
            $(".checkbox-wrapper").click(function () {
                if ($(this).children("input").prop("checked")) {
                    // uncheck
                    $(this).children("input").prop("checked", false);
                    $(this).removeClass("checked");
                    $(this).addClass("unchecked");
                    $(this).children("label").text("Private?");
                } else {
                    // check
                    $(this).children("input").prop("checked", true);
                    $(this).removeClass("unchecked");
                    $(this).addClass("checked");
                    $(this).children("label").text("Private");
                }
            });
        }
    }
};

/**
 * Run initialization functions on DOM-ready.
 *
 * Note that if we're waiting on other scripts to load, this won't happen
 * until that's done. To load scripts asynchronously without delaying setup,
 * don't start them loading until after DOM-ready time!
 */
$(function () {
    SN.Init.AjaxForms();
    SN.Init.UploadForms();
    SN.Init.CheckBoxes();
    if ($('.' + SN.C.S.FormNotice).length > 0) {
        SN.Init.NoticeForm();
    }
    if ($('#content .notices').length > 0) {
        SN.Init.Notices();
    }
    if ($('#content .entity_actions').length > 0) {
        SN.Init.EntityActions();
    }
    if ($('#profile_search_results').length > 0) {
        SN.Init.ProfileSearch();
    }
    if ($('.user_profile_tags .editable').length > 0) {
        SN.Init.PeopleTags();
    }
});

