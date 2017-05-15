window.hotspots = {};

// prevent errors while logging to browsers that support it
if (!window.console) {
    window.console = {
        log: function () {
        }
    };
}

;(function ($) {

    function Hotspots() {

        var that = this;

        $.extend(that, {

            _construct: function () {

                // bind behaviour to buttons
                $(document).on('click', '.face-detection-activate', function () {
                    that.set_context(this);
                    that.get_image(that.detect_faces);
                });
                $(document).on('click', '.add-hotspots', function () {
                    that.set_context(this);
                    that.get_image(that.add_hotspots);
                });

            },

            attachment_id: null,
            el: null,
            image: null,
            hidden: null,
            images: null,
            $context: null,
            $status_box: null,
            hotspots: [],
            faces: [],

            set_context: function (el) {
                that.el = el;
                that.attachment_id = $(el).data('attachment-id');
                that.$ui = $(el).parents('.face-detection-ui');
                that.$context = $(el).parents('.face-detect-panel');
                that.$status_box = that.$context.find('.status');
            },

            // request full image
            get_image: function (callback) {
                callback = callback || function () {
                        return false;
                    };

                if (that.image && that.$ui.find('.face-detection-image img').length) {
                    return callback();
                }

                that.update_status('Loading full size image', true);
                $.post(meauh.ajax_url, {
                    action: 'meauh_get_image',
                    nonce: meauh.get_image_nonce,
                    attachment_id: that.attachment_id
                }, function (response) {
                    if (response.success) {
                        // set our image
                        that.image = new Image();

                        // save for later
                        that.images = response.data;

                        // set source to original uncropped image
                        that.image.src = response.data.original[0];

                        $(that.image)
                            .appendTo('.face-detection-image')
                            .on('load', function () {
                                that.update_status('Image loaded');

                                // add our large off-screen sampler for pixastic etc...
                                if (!$('.face-detect-large-hidden').length) {
                                    $('body').append('<img class="face-detect-large-hidden" src="" alt="" />');
                                }
                                $('.face-detect-large-hidden').attr('src', response.data.original[0]);

                                // show current data
                                that.show_existing($('.face-detection-image').data('hotspots'));
                                that.show_existing($('.face-detection-image').data('faces'), 'face');

                                return callback();
                            });

                    }
                }, 'json');

                return false;
            },

            update_status: function (status, loading) {
                loading = loading || false;
                that.$status_box.html(status);
                if (loading) {
                    that.$status_box.addClass('loading');
                } else {
                    that.$status_box.removeClass('loading');
                }
            },

            detect_faces: function () {

                // Remove the previous copy, end up with one for every button press otherwise.
                $('.face-detect-large-hidden-copy').remove();

                var $found_box = that.$context.find('.found-faces'),
                    image = $('.face-detect-large-hidden').get(0),
                    image_copy = $(image)
                        .clone()
                        .removeClass('face-detect-large-hidden')
                        .addClass('face-detect-large-hidden-copy')
                        .appendTo('body')
                        .get(0);

                if ($(that.el).hasClass('has-faces')) {

                    $(image_copy).remove();
                    //$found_box.html( '' );
                    $(that.el)
                        .removeClass('has-faces')
                        .html('Detect faces');

                    $('.face-detection-image')
                        .data('faces', '')
                        .find('.face')
                        .remove();

                    return that.save({faces: 0});
                }

                // face detection
                return $(image_copy).faceDetection({
                    complete: function (faces) {
                        // update status - found faces
                        that.faces = faces;

                        if (!that.faces.length) {
                            that.update_status('No faces were found');
                            return;
                        }

                        // allow removal of found faces
                        $(that.el)
                            .addClass('has-faces')
                            .html('Forget found faces');

                        that.update_status('Found ' + that.faces.length + ' faces, re-cropping thumbnails', true);

                        that.show_existing(that.faces, 'face');

                        // cleanup
                        $(image_copy).remove();

                        // save data & regen
                        that.save({faces: that.faces});
                    },
                    error: function (img, code, message) {
                        // update status - error, message
                        console.log('error', message, img);
                        that.update_status('Error (' + code + '): ' + message);
                    }
                });

            },

            show_existing: function (data, type) {
                type = type || 'normal';

                var width = $(that.image).width(),
                    correction = that.images.original[1] / width,
                    hotspot_width;

                if ('undefined' !== typeof data && data.length) {
                    $.each(data, function (i, hotspot) {
                        that.add_hotspot({
                            x: (hotspot.x / correction),
                            y: (hotspot.y / correction),
                            width: hotspot.width / correction,
                            type: type
                        });
                    });
                }
            },

            add_hotspots: function () {

                var width = $(that.image).width(),
                    hotspot_width = width * 0.15,
                    correction = that.images.original[1] / width;

                // activate hotspots
                if (!$('.face-detection-image').hasClass('active')) {

                    // edit button
                    $(that.el)
                        .addClass('active')
                        .html('Finish adding hotspots');

                    that.$ui.find('button').not(that.el).attr('disabled', 'disabled');

                    that.update_status('Click on the image below to add hotspots. Clicking a hotspot will remove it.');

                    // bind hotspot toggling
                    $(that.image).on('click.hotspots', that.hotspot_click);

                    $('.face-detection-image').addClass('active');

                    // deactivate & save
                } else {

                    // edit button
                    $(that.el)
                        .removeClass('active')
                        .html('Edit hotspots');

                    // remove hotspot toggling
                    $(that.image).off('click.hotspots');

                    that.hotspots = [];
                    $('.face-detection-image .hotspot').not('.face').each(function () {
                        that.hotspots.push({
                            width: Math.round($(this).width() * correction),
                            x: Math.round(( $(this).position().left ) * correction),
                            y: Math.round(( $(this).position().top ) * correction)
                        });
                    });

                    $('.face-detection-image').removeClass('active');

                    if (!that.hotspots.length) {
                        that.hotspots = 0;
                    }

                    // save data
                    that.save({hotspots: that.hotspots});

                }

            },

            hotspot_click: function (e) {

                var width = $(that.image).width(),
                    hotspot_maxwidth = 150,
                    hotspot_width = width * 0.15 > hotspot_maxwidth ? hotspot_maxwidth : width * 0.15,
                    hotspot_offset = hotspot_width / 2;

                // Firefox doesn't do offsetX/Y so need to do something a little more complex
                that.add_hotspot({
                    x: ( e.offsetX || e.clientX - ( $(e.target).offset().left - window.scrollX ) ) - hotspot_offset,
                    y: ( e.offsetY || e.clientY - ( $(e.target).offset().top - window.scrollY ) ) - hotspot_offset
                });

            },

            add_hotspot: function (hotspot) {

                var width = $(that.image).width(),
                    height = $(that.image).height(),
                    $parent = $('.face-detection-image'),
                    hotspot_maxwidth = 150,
                    hotspot_width = width * 0.15 > hotspot_maxwidth ? hotspot_maxwidth : width * 0.15;

                hotspot = $.extend({
                    x: 0,
                    y: 0,
                    width: hotspot_width, // default 15% wide, max-width 120px
                    type: 'normal'
                }, hotspot);

                // Prevent hotspots from being placed outside edges of image.
                hotspot.x = Math.max((0 - (hotspot.width / 2)), Math.min(hotspot.x, (width - (hotspot.width / 2))));
                hotspot.y = Math.max((0 - (hotspot.width / 2)), Math.min(hotspot.y, (height - (hotspot.width / 2))));

                $('<div class="hotspot ' + hotspot.type + '"></div>')
                    .css({
                        left: ( ( hotspot.x / width ) * 100 ) + '%',
                        top: ( ( hotspot.y / height ) * 100 ) + '%',
                        width: ( ( hotspot.width / width ) * 100 ) + '%',
                        paddingBottom: ( ( hotspot.width / width ) * 100 ) + '%'
                    })
                    .attr('title', hotspot.type === 'normal' ? 'Click to toggle on/off' : '')
                    .appendTo($parent)
                    .click(function () {
                        if (!$(this).hasClass('face') && $parent.hasClass('active')) {
                            $(this).remove();
                        }
                    });

            },

            // show a cropped thumbnail preview
            preview: function () {

                var $previews = $('.post-thumbnail-preview img'),
                    previews_length = $previews.length;

                that.update_status('Updating preview', true);

                $previews.each(function (i) {
                    if (!that.images[$(this).data('size')]) {
                        return;
                    }

                    $(this)
                        .fadeTo(300, 0.25)
                        .attr('src', that.images[$(this).data('size')][0] + '?t=' + new Date().getTime())
                        .on('load', function () {
                            $(this).fadeTo(300, 1);
                            if (i === previews_length - 1) {
                                that.update_status('');
                            }
                        });
                });

            },

            save: function (data) {

                that.update_status('Re-cropping thumbnails', true);

                that.$ui.find('button').attr('disabled', 'disabled');

                $.post(meauh.ajax_url, $.extend({
                    action: 'meauh_save_image',
                    nonce: meauh.save_image_nonce,
                    attachment_id: that.attachment_id
                }, data), function (response) {
                    if (response.success) {

                        that.update_status('Thumbnails re-cropped');

                        $.extend(that.images, response.data.resized);

                        that.preview();

                    } else {

                        that.update_status('No thumbnails were re-cropped');

                    }

                    that.$ui.find('button').removeAttr('disabled');
                }, 'json');

            }


        });

        // initialise
        that._construct();

        return that;

    }

    // initialise
    window.hotspots = new Hotspots();

}(jQuery));
