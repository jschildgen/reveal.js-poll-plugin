/*
 * Poll Plugin
 *
 * By Johannes Schildgen, 2020
 * https://github.com/jschildgen/reveal.js-poll-plugin
 * 
 */
 x= null;
var Poll = (function(){

var refresh_interval = null;
var current_poll = null;

function show_status() {
    $.get( "poll/proxy.php/?method=status", function( data ) { 
        res = JSON.parse(data);
        if(!('count' in res)) { return; } // no active poll
        $(current_poll).find("> .poll-responses").html(res.count == 0 ? "" : res.count);
    });
}

function start_poll() {
    var question = $(current_poll).children("h1").text();

    var answers = [];
    $(current_poll).find("ul > li > .poll-answer-text").each(function(i) {
        answers.push(this.innerHTML);
    });

    var correct_answers = [];
    $(current_poll).find("ul > li[data-poll='correct'] > .poll-answer-text").each(function(i) {
        correct_answers.push(this.innerHTML);
    });

    data = { "question" : question, "answers": answers, "correct_answers": correct_answers };

    $.get( "poll/proxy.php/?method=start_poll&data="+encodeURIComponent(JSON.stringify(data)), function( res ) { });
    refresh_interval = window.setInterval(show_status, 1000);
}

function stop_poll() { 
    if(current_poll == null) { return; }
    clearInterval(refresh_interval);
    $(current_poll).find("ul > li > .poll-percentage").css("width","0%");
    $.get( "poll/proxy.php/?method=stop_poll", function( data ) { 
        res = JSON.parse(data);
        var total = 0;
        for(i in res.answers) {
            total += res.answers[i];
        }
        $(current_poll).find("ul > li > .poll-percentage").each(function(i) {
            percentage = (""+i in res.answers) ? 100*res.answers[i]/total : 0;
            $(this).css("width",percentage+"%");
        })

        $(current_poll).find("ul > li[data-poll='correct'] > .poll-answer-text").css("font-weight", "bold");
        $(current_poll).find("ul > li[data-poll='correct'] > .poll-answer-text").each(function(i) { $(this).html("&rightarrow; "+$(this).html()+" &leftarrow;")});
        current_poll = null;
    });
}

Reveal.addEventListener( 'fragmentshown', function( event ) {
    if(!$(event.fragment).hasClass("poll")) { return; }
    current_poll = event.fragment;
    start_poll();
} );


return {
    init: function() {    
        if(window.location.search.match( /print-pdf/gi )) {
            /* don't show poll in print view */
            return;
        }

        $(".poll > ul > li").not(":has(>span)").click(function() { 
            stop_poll();
        });

        $(".poll > ul > li").not(":has(>span)").each(function(i) {
            this.innerHTML = '<span class="poll-percentage"></span>'
                            +'<span class="poll-answer-text">'+this.innerHTML+'</span>';
        });

        $(".poll").not(":has(>.poll-responses)").each(function(i) { 
            $(this).append('<span class="poll-responses"></span>');
        });

        $(".poll").show();
    }
}

})();

Reveal.registerPlugin( 'poll', Poll );