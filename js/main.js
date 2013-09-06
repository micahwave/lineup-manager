var lineupManager = {};

(function($){

	lineupManager = {

		$lineup: $('#lineup'),

		init: function() {

			var t = this;

			// add post when select changes
			t.$lineup.find('select').change(function(){
				t.add_item( $(this).val() );
			});

			// make ol sortable
			t.$lineup.find('.selected-posts').sortable({
				placeholder: 'placeholder',
				update: function(ui, e) {
					t.serialize();
				}
			});

			// remove link
			t.$lineup.on('click', '.remove', function(e){
				e.preventDefault();
				t.remove_item( $(this).closest('li').data('id') );
			});

			// search button
			$('#lineup-search-submit').click(function(){
				t.search( $('#lineup-search-query').val() );
			});

			t.$lineup.on('click', '.search-results .add', function(e){
				e.preventDefault();
				t.add_item( $(this).data('id') );
				$(this).closest('.item').remove();
			});

		},

		add_item: function( id ) {

			var t = this;

			if( t.$lineup.find('li[data-id="' + id + '"]').length ) {

				alert('The post you selected has already been added. Please select a different post.');

			} else {

				$.post(
					ajaxurl,
					{
						action: 'lineup_manager_get_item',
						id: id,
						_ajax_nonce: 'todo'
					},
					function( response ) {

						if( response != 0 ) {

							if( t.$lineup.find('.notice').length ) {
								t.$lineup.find('.notice').hide();
							}

							t.$lineup.find('.selected-posts').append(response);

							t.serialize();
						}
					}
				);

			}
		},

		remove_item: function( id ) {

			var t = this;

			t.$lineup.find('li[data-id="' + id + '"]').remove();

			if( t.$lineup.find('li').length == 0 ) {
				t.$lineup.find('.notice').show();
			}

			t.serialize();
		},

		serialize: function() {

			var t = this, ids = [];
					
			t.$lineup.find('li').each(function(){
				ids.push( $(this).data('id') );
			});

			$('#lineup-post-ids').val( ids.join(',') );
		},

		search: function( query ) {

			var t = this;
			
			$.post(
				ajaxurl,
				{
					action: 'lineup_manager_get_posts',
					query: query,
					_ajax_nonce: $('#nonce').val()
				},
				function( response ) {
					if( response != 0 ) {
						t.$lineup.find('.search-results').html( response );
					}
				}
			);
		}
	}

	lineupManager.init();

})(jQuery)