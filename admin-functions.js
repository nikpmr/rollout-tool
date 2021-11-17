jQuery(function ($) {
	SiupAdmin = {
		pages:{
			loadPageContent: function (element) {
				var content = $(element).find(':selected').attr('data-content');
				if (typeof content == 'undefined') return false;
				content = content.replace(/</g, '&#60;').replace(/>/g, '&#62;')
				content = content.replace(/\[/g, '<').replace(/\]/g, '>');

				var $content = $('<section>' + content + '</section>');
				$content.find('*').each(function(){ // Add IDs and classes to content
					if(this.getAttribute('module_class') != null) $(this).attr('class',  this.getAttribute('module_class'));
					if(this.getAttribute('module_id') != null) $(this).attr('id',  this.getAttribute('module_id'));
				});
				console.log($content.get(0)); // Output to console
				var $selector = $(element).closest('.siup_item').find('.siup_item_selector');
				$selector.data('content', $content);
			},
			loadNodeContent: function (element) {
				var $selector = $(element).closest('.siup_item').find('.siup_item_selector');
				if($selector.val() == '') return false;
				$selector.val( $selector.val().replace(/^section > /,'') );
				var $content = $selector.data('content');
				if (typeof $content == 'undefined') return false;
				$contentElement = $content.find($selector.val());
				if($(element).hasClass('siup_item_selector')) console.log($contentElement.get(0));
				var $attributes = $(element).closest('.siup_item').find('.siup_item_attributes');
				$attributes.empty();
				var attributes = $contentElement.get(0).attributes || [];
				for (var i in attributes) {
					if (typeof attributes[i].value != 'undefined') {
						var encodedValue = encodeURIComponent(attributes[i].value);
						$attributes.append(`
							<option value="${attributes[i].name}:${encodedValue}">
								${attributes[i].name}: ${attributes[i].value}
							</option>
						`);
					}
				}
				var $action = $(element).closest('.siup_item').find('.siup_item_action');
				$action.data('content', $contentElement);
				var $attributesSection = $(element).closest('.siup_item').find('.siup_item_attributes_section');
				if($action.val() == 'replace_attr_custom' || $action.val() == 'delete_attr') 
					$attributesSection.show();
				else $attributesSection.hide();

				// Include global module number, if there is one
				var globalModule = $contentElement.closest('[global_module]').attr('global_module');
				if(typeof globalModule != 'undefined')
					$action.data('globalModule', globalModule);
				else $action.data('globalModule', '');
			},
			addInstruction: function () {
				var $instruction = $('.siup_item.template').clone()
				.removeClass('template')
				.appendTo('.siup_items');
			},
			deleteInstruction:function(element){
				$(element).closest('.siup_item').remove();
			},
			apply:function(){
				var confirmText = 'The above instructions will be performed on all selected sites. Are you sure?';
				if(!confirm(confirmText)) return false;
				$('.siup_progress').html('Applying...');
				var itemsArray = [];
				$('.siup_item').not('.template').each(function(){
					itemsArray.push({
						pageId: 			$(this).find('.siup_item_page').val(),
						pageName:			$(this).find('.siup_item_page option:selected').attr('data-name'),
						pageType:			$(this).find('.siup_item_page option:selected').attr('data-type'),
						pageTitle:			$(this).find('.siup_item_page option:selected').html(),
						selector: 			$(this).find('.siup_item_selector').val(),
						element:			$(this).find('.siup_item_action').data('content').get(0).outerHTML,
						content:			$(this).find('.siup_item_action').data('content').text(),
						action: 			$(this).find('.siup_item_action').val(),
						attributes:			$(this).find('.siup_item_attributes').val(),
						attributesAll:		Array.from($(this).find('.siup_item_action').data('content').get(0).attributes).map(function(currentValue){return currentValue.name + ':' + encodeURIComponent(currentValue.value)}),
						globalModule: 		$(this).find('.siup_item_action').data('globalModule')
					});
				});
				$.post(SiupAdminVars.pluginPath + 'update-pages.php', {
					target_sites:	$('.siup_target_sites').val(),
					items:			itemsArray
				}, function(data){
					$('.siup_progress').html('Done. Check the developer console for details.');
					console.log(data);
				});
			}
		},
		db:{
			updateSource:function(){
				var href = window.location.href 
				+ '&method=update_source'
				+ '&table=' + $('.source_table').val()
				+ '&column_name=' + $('.source_table_column_name').val()
				+ '&column_value=' + $('.source_table_column_value').val();
				window.location = href;
			},
			displaySetting:function(element){
				var currentValue = this.decodeFromDb($(element).find('option:selected').attr('data-current-value'));
				console.log(currentValue);
				var subSettingColumn = (typeof currentValue === 'object') ? 'visible' : 'hidden';
				if(subSettingColumn == 'visible'){
					var $subSettingSelect = $(element).closest('tr').find('.sub_setting_column select');
					var subSettingSelectValue = '<option value="">-- Update entire value --</option>';
					for(var val of Object.keys(currentValue)){
						subSettingSelectValue += `
							<option value="${val}" data-current-sub-value="${currentValue[val]}">${val}</option>
						`;
					}
					$subSettingSelect.html(subSettingSelectValue).data('object', currentValue);
				}
				$(element).closest('tr').find('.sub_setting_column').css('visibility', subSettingColumn);
			},
			displaySubSetting:function(element){
				var currentSubValue = $(element).find('option:selected').attr('data-current-sub-value');
				console.log(currentSubValue);
			},
			apply:function(){
				var confirmText = 'The above database changes will be performed on all selected sites. Are you sure?';
				if(!confirm(confirmText)) return false;
				$('form .siup_progress').html('Applying...');
				var itemsArray = [];
				$('.siup_item').not('.template').each(function(){
					itemsArray.push({
						settingName: 		$(this).find('.siup_item_db_name').val(),
						settingValue:		SiupAdmin.db.decodeFromDb($(this).find('.siup_item_db_name option:selected').attr('data-current-value')),
						isSerialized: 		($(this).find('.siup_item_db_name option:selected').attr('data-is-serialized') == '1'),
						subSettingName: 	$(this).find('.siup_item_db_sub').val(),
						subSettingValue: 	$(this).find('.siup_item_db_sub option:checked').attr('data-current-sub-value')
					});
				});
				$.post(SiupAdminVars.pluginPath + 'update-db.php', {
					target_sites:	$('.siup_target_sites').val(),
					source_table: 	$('.source_table').val(),
					column_name: 	$('.source_table_column_name').val(),
					column_value: 	$('.source_table_column_value').val(),
					items:			itemsArray
				}, function(data){
					$('form .siup_progress').html('Done. Check the developer console for details.');
					console.log(data);
				});
			},
			runQuery: function(){
				var confirmText = 'The query will be run on all selected sites. Ensure the query is correct before proceeding.';
				if(!confirm(confirmText)) return false;
				$('.siup_query .siup_progress').html('Applying...');
				$.post(SiupAdminVars.pluginPath + 'update-db.php', {
					method:			$('.siup_query_method').val(),
					target_sites:	$('.siup_target_sites').val(),
					source_table: 	$('.source_table').val(),
					data:			$('.siup_query_data').val(),
					where:			$('.siup_query_where').val()
				}, function(data){
					$('.siup_query .siup_progress').html('Done. Check the developer console for details.');
					console.log(data);
				});
			},
			encodeForDb: function(jsonData){
				if(typeof jsonData === 'object') jsonData = JSON.stringify(jsonData);
				jsonData = encodeURIComponent(jsonData);
				return(jsonData);
			},
			decodeFromDb: function(dbData){
				dbData = decodeURIComponent(dbData);
				if(this.isJson(dbData)) dbData = JSON.parse(dbData);
				return(dbData);
			},
			isJson:function(item){
				item = typeof item !== "string" ? JSON.stringify(item) : item;
				try {
					item = JSON.parse(item);
				} catch (e) {
					return false;
				}
				if (typeof item === "object" && item !== null) return true;
				return false;
			}
		},
		clone:{
			changeAction:function(element){
				var $clone = $(element).closest('.siup_item').find('.siup_item_clone_name_clone');
				var $delete = $(element).closest('.siup_item').find('.siup_item_clone_name_delete');
				if($(element).val() == 'clone'){
					$clone.show();
					$delete.hide();
				}
				else if($(element).val() == 'delete'){
					$clone.hide();
					$delete.show();
				}
			},
			apply:function(){
				var confirmText = 'The above database changes will be performed on all selected sites. Are you sure?';
				if(!confirm(confirmText)) return false;
				$('.siup_progress').html('Applying...');
				var itemsArray = [];
				$('.siup_item').not('.template').each(function(){
					var action = $(this).find('.siup_item_clone_action').val();
					itemsArray.push({
						action:				action,
						settingName: 		(action == 'clone') ? $(this).find('.siup_item_clone_name_clone').val() : $(this).find('.siup_item_clone_name_delete').val(),
						settingId:			(action == 'clone') ? $(this).find('.siup_item_clone_name_clone option:selected').attr('data-id') : '',
					});
				});
				$.post(SiupAdminVars.pluginPath + 'update-clone.php', {
					target_sites:		$('.siup_target_sites').val(),
					source_table: 		$('.source_table').val(),
					column_name: 		$('.source_table_column_name').val(),
					key_column_name:	$('.source_table_key_column_name').val(),
					items:				itemsArray
				}, function(data){
					$('.siup_progress').html('Done. Check the developer console for details.');
					console.log(data);
				});
			}
		}
	}
});