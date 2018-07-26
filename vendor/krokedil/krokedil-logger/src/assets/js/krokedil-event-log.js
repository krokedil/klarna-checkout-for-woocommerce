jQuery( function( $ ) {
    var krokedil_event_log = { 
        renderJson: function() {
            $(".krokedil_json").each(function(){
                var string = $( this ).html();
                try {
                    var json = JSON.parse( string );
                    renderjson;
                    $( this ).html( renderjson.set_show_to_level( '2' )( json ) );
                } 
                catch {
                    console.log( 'Error parsing JSON' );
                }
            });
        },
        toggleJson: function( event_nr ){
            console.log( 'in function');
            var event_id = '#krokedil_event_nr_' + event_nr;
            console.log( event_id );
            if( $( event_id ).hasClass( 'krokedil_hidden' ) ) {
                $( event_id ).removeClass( 'krokedil_hidden' );
                $( event_id ).addClass( 'krokedil_shown' );
            } else {
                $( event_id ).removeClass( 'krokedil_shown' );
                $( event_id ).addClass( 'krokedil_hidden' );
            }
        }
    }
    $( document ).ready(function() {
        krokedil_event_log.renderJson();
    });
    $('body').on('click', '.krokedil_timestamp', function() {
        console.log( 'click' );
        var event_nr = $(this).data('event-nr');
        console.log(event_nr)
        console.log($(this));
        krokedil_event_log.toggleJson( event_nr );
    });
});