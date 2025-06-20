// File: app.js (hoặc nội dung mới của socket.js)

$(document).ready(function() {

    // --- Các biến và hằng số cho giao diện ---
    const active = $('.page-content').attr("data-active");
    const avatar = $('.page-content').attr("data-images");

    // --- TOÀN BỘ CÁC HÀM CẬP NHẬT GIAO DIỆN CỦA BẠN ---
    // (Copy y hệt từ file gốc của bạn)

    function social_like(data) {
        var $this = $('body').find(".page-socials");
        var html = $this.find(".social-item[data-active='" + data.router + "']");
        if (data.code == 'send') {
            $this.find(".spinner-load").show();
        } else {
            if (data.status == 'error') {
                swal_error(data.data.content);
                topbar.hide();
                $this.find("button").removeAttr('disabled');
            } else {
                topbar.hide();
                $this.find("button").removeAttr('disabled');
                html.find(".social-like").text(data.data.like);
                if (data.data.content == 'unlike') {
                    html.find(".btn-social-like i").removeClass('text-danger ti-heart-filled').addClass('ti-heart');
                } else {
                    html.find(".btn-social-like i").addClass('text-danger ti-heart-filled').removeClass('ti-heart');
                }
            }
        }
    }

    function social_comment(data) {
        var $this = $('body').find(".page-socials");
        var html = $this.find(".social-item[data-active='" + data.router + "']");
        if (data.code == 'send') {
            $this.find(".spinner-load").show();
        } else {
            if (data.status == 'error') {
                swal_error(data.data.content);
                topbar.hide();
                $this.find("button").removeAttr('disabled');
            } else {
                topbar.hide();
                $this.find("button").removeAttr('disabled');
                html.find(".social-comment").text(data.data.comment);
                var newComment = $('<div class="d-flex social-comment-item align-items-top mb-3">' +
                    '<div class="me-2"><img data-src="/' + data.data.avatar + '?type=thumb" alt="' + data.data.account + '" class="rounded-circle lazyload" style="width: 40px;"></div>' +
                    '<div class="d-flex flex-column"><div class="bg-body-tertiary rounded-4 p-2"><div class="fw-bold">' + data.data.account + '</div><div class="py-1">' + data.data.content + '</div></div><small class="text-muted">' + data.data.date + '</small></div>' +
                    '</div>');
                html.find(".social-comments-list").prepend(newComment);
                html.find('.social-comment-content').val('');
            }
        }
    }

    function write(data) {
        var $this = $('body').find(".page-write");
        var html = $this.find(".content-result");
        if (data.code == 'send') {
            $this.find(".spinner-load").show();
        } else {
            if (data.status == 'error') {
                swal_error(data.data.content);
                $this.find(".spinner-load").hide();
                topbar.hide();
                $this.find("button").removeAttr('disabled');
            } else {
                if (data.completed != 'DONE') {
                    $this.find(".spinner-load").hide();
                    topbar.hide();
                    $this.find("button").removeAttr('disabled');
                    html.append(data.data.content);
                } else {
                    var getcontent = html.html();
                    var updatedContent = processMessageContent(getcontent);
                    html.html(updatedContent);
                    setTimeout(() => { Prism.highlightAll(); }, 100);
                }
            }
        }
    }

    function audio(data) {
        var $this = $('body').find(".page-audio");
        var html = $this.find(".content-result");
        if (data.code == 'send') {
            $this.find(".spinner-load").show();
        } else {
            if (data.status == 'error') {
                swal_error(data.data.content);
                $this.find(".spinner-load").hide();
                topbar.hide();
                $this.find("button").removeAttr('disabled');
            } else {
                $this.find(".spinner-load").hide();
                topbar.hide();
                $this.find("button").removeAttr('disabled');
                $.each(data.data, function(index, value) {
                    var newAudio = $('<div class="border bg-opacity-10 d-flex text-start shadow align-items-center justify-content-start p-0 position-relative rounded-pill w-100 mb-3">' +
                                        '<div class="audio-item btn w-100 p-2 rounded-pill w-100 text-start "  data-audio="' + value.audio + '">' +
                                            '<div class="d-flex justify-content-between align-items-center">' +
                                                '<img data-src="' + value.images + '?type=thumb" class=" w-50px rounded-circle shadow lazyload audio-images">' +
                                                '<div class="w-100 position-relative ms-3"><strong class="title">' + value.name + '</strong></div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="position-absolute end-0"><div class="d-flex justify-content-end me-3 align-items-center"><div class="d-flex justify-content-end"><div class="dropdown me-2"><button class="btn btn-light w-50px h-50px rounded-circle d-flex justify-content-center align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-share me-2"></i></button><ul class="dropdown-menu rounded-4 bg-body border-0 shadow"><li><button class="dropdown-item" data-click="modal" data-multi="share-social" data-url="/social/create-data/audio-song/' + value.active + '"><i class="ti ti-social me-2"></i>Chia sẽ cộng đồng</button></li><li><button class="dropdown-item"><i class="ti ti-share me-2"></i>Chia sẽ ngoài</button></li></ul></div><a href="/audio/download/' + value.active + '" download="' + value.name + '.mp3" class="btn btn-light w-50px h-50px rounded-circle d-flex justify-content-center align-items-center"><i class="ti ti-download fs-5"></i></a></div></div></div>' +
                                     '</div>');
                    html.prepend(newAudio);
                });
                stopaudio();
                playaudio();
            }
        }
    }

    function messages(data) {
        if (data.code == 'send') {
            checkmessages(data.character, data.data);
        } else {
            receivemessages(data.type, data.character, data.data, data.sender, data.completed);
        }
    }

    function images(data) {
        var $this = $('body').find(".page-images");
        if (data.code == 'send') {
            var newImages = $('<div class="col-lg-3 col-6 items-images "><div class="card card-hover shadow rounded-4"><div class="card-body p-2"><div class="row g-0 content-result h-200px"><div class="col-12 h-100 text-center"><div class="rounded-4 h-100 d-flex justify-content-center align-items-center"><img src="/templates/assets/img/logo-fade.svg" class="w-25"></div></div></div></div></div></div>');
            $this.find(".items-images-list").prepend(newImages);
        } else {
            if (data.status == 'error') {
                swal_error(data.data.content);
                topbar.hide();
                $this.find("button").removeAttr('disabled');
                $this.find(".items-images-list .items-images:first").remove();
            } else {
                $this.find(".items-images-list .items-images:first .content-result").html('<div class="col-12">' +
                    '<div class="position-relative d-flex justify-content-center align-items-center">' +
                    '<div class="position-absolute d-flex top-0 justify-content-center align-items-center h-200px items-images-button">' +
                    '<button class="btn shadow-lg btn-sm btn-light rounded-circle mx-1 h-30px w-30px d-flex justify-content-center align-items-center" data-click="modal" data-url="/users/content/views/' + data.data.active + '"><i class="ti ti-eye fs-5"></i></button>' +
                    '<a class="btn shadow-lg btn-sm btn-light rounded-circle mx-1 h-30px w-30px d-flex justify-content-center align-items-center" href="' + data.data.images + '" download><i class="ti ti-download fs-5"></i></a>' +
                    '<button class="btn shadow-lg btn-sm btn-light rounded-circle mx-1 h-30px w-30px d-flex justify-content-center align-items-center" data-click="modal" data-url="/users/content/delete/' + data.data.active + '"><i class="ti ti-trash fs-5"></i></button>' +
                    '</div></div>' +
                    '<div class="item-image rounded-4 lazyload" data-size="200" data-bgset="' + data.data.images + '"></div>' +
                    '<a href="#" class="stretched-link d-lg-none" data-click="modal" data-url="/users/content/views/' + data.data.active + '"></a>' +
                    '</div>');
                topbar.hide();
                $this.find("button").removeAttr('disabled');
            }
        }
    }
    
    function prompt_images(data){
        var $this = $('body').find(".page-images");
        if (data.status == 'error') {
            swal_error(data.data.content);
            topbar.hide();
            $this.find("button, #prompt").removeAttr('disabled');
        } else {
            topbar.hide();
            $this.find("button, #prompt").removeAttr('disabled');
            $this.find("#prompt").val(data.data.prompt);
        }
    }

    function prompt_audio(data){
        var $this = $('body').find(".page-audio");
        if (data.status == 'error') {
            swal_error(data.data.content);
            topbar.hide();
            $this.find("button, #prompt, #title, #style").removeAttr('disabled');
        } else {
            topbar.hide();
            $this.find("button, #prompt, #title, #style").removeAttr('disabled');
            $this.find("#prompt").val(data.data.lyric);
            $this.find("#title").val(data.data.title);
            $this.find("#style").val(data.data.style);
        }
    }

    function receivemessages(type, character, data, sender, completed) {
        var getcharacter = $("body").find('.page-messages[data-active="' + character + '"]');
        if (getcharacter.length > 0) {
            var messages_list = getcharacter.find(".messages-body");
            var existingMessage = messages_list.find('.messages-content-item[data-time="' + data.date + '"]');

            if (existingMessage.length > 0) {
                existingMessage.find('.content-result').append(data.content);
            } else {
                var newMessage = $('<div class="d-flex align-items-start my-2 messages-content-item" data-time="' + data.date + '">' +
                    '<img data-src="' + data.avatar + '?type=thumb" class="w-30px rounded-circle rounded-3 me-2 lazyload">' +
                    '<div class="d-inline-block w-max-90 content-result">' + data.content + '</div>' +
                    '</div>');
                messages_list.append(newMessage);
                // Cập nhật lại existingMessage sau khi tạo mới
                existingMessage = newMessage; 
            }
            
            getcharacter.find('.messages-content-input').focus();

            if (completed == 'DONE' && existingMessage.length > 0) {
                MathJax.typesetPromise();
                var getcontent = existingMessage.find('.content-result').html();
                var updatedContent = processMessageContent(getcontent);
                existingMessage.find('.content-result').html(updatedContent);
                setTimeout(() => { Prism.highlightAll(); }, 100);
            }
            typing('messages-typing', character, { "data": data.typing }, active, 'bot');
        }
    }

    function processMessageContent(content) {
        const codeBlockRegex = /```([^\s]+)?\n([\s\S]*?)```/g;
        return content.replace(codeBlockRegex, function(match, language, code) {
            const lang = language || 'default';
            const langClass = `language-${lang}`;
            // Dùng- $('<div/>').text(code).html() - để escape HTML entities trong code
            const escapedCode = $('<div/>').text(code.trim()).html();
            return `<pre class="${langClass}"><code class="${langClass}">${escapedCode}</code></pre>`;
        });
    }

    function sendmessages(character, data, check = null) {
        var getcharacter = $("body").find('.page-messages[data-active="' + character + '"]');
        var messages_list = getcharacter.find(".messages-body");
        if (getcharacter.length > 0) {
            var checked = (check === 'true') ? '<i class="ti ti-checks fs-6"></i>' : '<div class="spinner-border w-15px h-15px" role="status"><span class="visually-hidden">Loading...</span></div>';
            messages_list.append('<div class="d-flex justify-content-end align-items-start my-3 messages-content-item">' +
                '<div class="position-relative"><div class="me-1 spinner-load d-block text-success">' + checked + '</div></div>' +
                '<div class="bg-danger-subtle text-body p-2 rounded-4 d-inline-block w-max-90">' + data.content + '</div>' +
                '<img data-src="/' + avatar + '?type=thumb" class="h-30px w-30px rounded-circle ms-2 lazyload">' +
                '</div>');
            
            // Cuộn xuống cuối
            messages_list.closest('.scroll-vh-100-y, body').animate({ scrollTop: messages_list[0].scrollHeight }, 100);
        }
    }

    function checkmessages(character, data) {
        var getcharacter = $("body").find('.page-messages[data-active="' + character + '"]');
        if (getcharacter.length > 0) {
            var lastMessage = getcharacter.find(".messages-content-item:last-child");
            var spinner = lastMessage.find(".spinner-load .spinner-border");
            if (spinner.length > 0) {
                spinner.parent().html('<i class="ti ti-checks fs-6"></i>');
            }
            typing('messages-typing', character, { "data": 'typing' }, active, 'bot');
        }
    }

    function typing(type, character, data, sender, bot = false) {
        var getcharacter = $("body").find('.page-messages[data-active="' + character + '"]');
        var typingIndicator = getcharacter.find(".messages-content-input-typing");
        if ((sender !== active || bot) && data.data === 'typing') {
            typingIndicator.show();
        } else {
            typingIndicator.hide();
            $('.page-messages .messages-content-button, .page-messages .messages-content-input').removeAttr("disabled");
        }
    }

    // --- LOGIC CHÍNH CỦA ỨNG DỤNG ---

    // 1. Khởi tạo dịch vụ WebSocket
    WebSocketService.init({
        url: 'wss://wsa.ellm.io/', // Thay đổi nếu cần
        activeId: active
    });

    // 2. Đăng ký các hàm xử lý cho từng loại tin nhắn
    WebSocketService.on('open', () => console.log("Hệ thống đã kết nối."));
    
    const eventMapping = {
        'write': write, 'nextWrite': write,
        'chat': messages,
        'txt2img': images, 'img2img': images, 'inpaint': images, 'removebg': images,
        'song': audio, 'cover': audio, 'voices': audio, 'clone': audio,
        'ai-prompt-images': prompt_images,
        'ai-prompt-audio': prompt_audio,
        'social-like': social_like,
        'social-comments': social_comment
    };

    for (const eventName in eventMapping) {
        WebSocketService.on(eventName, eventMapping[eventName]);
    }

    // 3. Xử lý sự kiện người dùng gửi tin nhắn
    $(document).on('keypress click', '.messages-content-input, .messages-content-button', function(e) {
        if ((e.type === 'keypress' && e.which === 13 && !e.shiftKey) || (e.type === 'click' && $(this).hasClass('messages-content-button'))) {
            e.preventDefault();
            
            var $page = $(this).closest('.page-messages');
            var $button = $page.find('.messages-content-button');
            var $input = $page.find('.messages-content-input');
            
            $button.attr("disabled", "disabled");
            $input.attr("disabled", "disabled");
            
            var character = $page.attr("data-active");
            var content = $input.val();
            
            if (content.trim() !== '') {
                var dataPayload = {
                    "content": content,
                    "lang": $page.find(".messages-lang").val(),
                    "style": $page.find(".messages-style").val(),
                    "date": 'Just Now',
                    "data": 'text',
                };
                
                sendmessages(character, dataPayload, null); 
                
                // Gửi dữ liệu qua WebSocketService
                WebSocketService.send({
                    "status": "success",
                    "sender": active,
                    "type": 'chat',
                    "stream": "true",
                    "character": character,
                    "data": dataPayload,
                    "code": 'send'
                });
                
                $input.val('');
            } else {
                swal_error('Not Empty');
                $button.removeAttr("disabled");
                $input.removeAttr("disabled");
            }
        }
    });
});