$(document).ready(function() {
  get_poll();
})

COLORS = ["success", "danger", "warning", "primary"];

current_qid = null;

function get_poll() {
  $.get( "./api/?method=get_poll", function( data ) {
    if(!('question' in data)) {   // no quiz active
      $('#question').html('- currently no poll running -');
      $('#answers').hide();
    } else if(current_qid == data.qid) {  // current question
      return;
    } else {    // new question
      current_qid = data.qid;
      $('#question').html(data.question);
      $('#answers').html('');
      for(i in data.answers) {
        $('#answers').append(
          ' <button onClick="respond('+i+')" class="element-animation1 btn btn-lg btn-'+COLORS[i%+COLORS.length]+' btn-block" name="q_answer" value="'+i+'">'
          //+ String.fromCharCode(97+parseInt(i)) + ') '
          +data.answers[i]+'</button>');
      }
      $('#answers').show();
      window.navigator.vibrate(200);
    }
  });
}

function respond(aid) {
  $('#answers > button').prop('disabled', true);
  $('#answers > button')[aid].innerHTML = '<b>&rightarrow; '+$('#answers > button')[aid].innerHTML+' &leftarrow;</b>';
  $.get( "./api/?method=respond&aid="+aid, function( data ) { });
}

window.setInterval(function(){
  get_poll();
}, 3000);
