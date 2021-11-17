<?php

function siup_register_options_pages(){
	add_menu_page(
		'Rollout Tool', 					// Title
		'Rollout Tool', 					// Menu option
		'manage_options', 					// Permission
		'siup_options_pages', 				// Slug 
		'siup_render_options_pages' 		// Render function
	);
	add_submenu_page(
		'siup_options_pages',		// Slug of parent item
		'Update Pages', 								
		'Update Pages', 								
		'manage_options', 						
		'siup_options_pages', 		
		'siup_render_options_pages'	
	);
	add_submenu_page(
		'siup_options_pages',		// Slug of parent item
		'Update DB Settings', 								
		'Update DB Settings', 								
		'manage_options', 						
		'siup_options_db', 		
		'siup_render_options_db'	
	);
	add_submenu_page(
		'siup_options_pages',		// Slug of parent item
		'Clone DB Entries', 								
		'Clone DB Entries', 								
		'manage_options', 						
		'siup_options_clone', 		
		'siup_render_options_clone'	
	);
}
add_action('admin_menu', 'siup_register_options_pages');

function siup_register_options(){ /* No options registered. */ }

function siup_render_options_pages(){
	?>
		<div class="wrap">
			<div class="siup_options">
				<h1>Rollout Tool</h1>
				<h3>Update Pages</h3>
				<h4>Specify instructions below to update pages across the entire multisite.</h4>
				<p>
					To use:
					<ol>
						<li>Select the target sites in need of updating. The current site will be used as the source.</li>
						<li>Click the Add Instruction button and open the developer console.</li>
						<li>Choose a page. A hierarchy of its shortcodes will appear in the DevTools panel.</li>
						<li>Locate the shortcode you'd like to change, then right click → Copy → Copy selector.</li>
						<li>Paste the selector into the Selector input box.</li>
						<li>Select an action. The selected action will be carried out on all pages on the multisite using the current site as a template.</li>
						<li>Repeat the steps above for each instruction, then click Apply Instructions to perform them for each page on the multisite.</li>
					</ol>
				</p>
				<form method="post" action="options.php">
					<div class="siup_target_sites_section">
						Target sites:
						<select multiple class="siup_target_sites">
							<?php
								$sites = wp_get_sites();
								foreach($sites as $site){
									if(
										$site['domain'] == 'exploremytown.com' || 
										$site['domain'] == 'template.exploremytown.com'
									) continue;

									?>
										<option value = "<?=$site['blog_id'];?>" >
											<?=$site['domain'];?>
										</option>
									<?php
								}
							?>
						</select>
					</div>
					<table class="form-table siup_items">
						<tr class="siup_item template">
							<td>
								Page: <br>
								<select class="siup_item_page" onchange="SiupAdmin.pages.loadPageContent(this)">
									<option value="">Choose a page</option>
									<?php 
										global $wpdb;
										$page_results = $wpdb->get_results("
											SELECT * FROM ". $wpdb->prefix . "posts 
											WHERE (
												post_type = 'page'
												OR post_type = 'et_pb_layout'
												OR post_type = 'et_header_layout'
												OR post_type = 'et_body_layout'
												OR post_type = 'et_footer_layout'
											)
											AND post_status = 'publish'
										", ARRAY_A);
										foreach($page_results as $page){
											?>
												<option 
													value="<?=$page['ID'];?>"
													data-name="<?=$page['post_name'];?>"
													data-type="<?=$page['post_type'];?>"
													data-content="<?=htmlentities($page['post_content']);?>"
												>
													<?=$page['post_title'];?> (<?=$page['post_type'];?>)
												</option>
											<?php
										}
									?>
								</select>
							</td>
							<td>
								Selector: <br>
								<input type="text" class="siup_item_selector" onblur="SiupAdmin.pages.loadNodeContent(this)" />
							</td>
							<td>
								Action: <br>
								<select class="siup_item_action" onchange="SiupAdmin.pages.loadNodeContent(this)">
									<option value="replace_all">Replace entire element</option>
									<option value="replace_content">Replace content only</option>
									<option value="replace_attr">Replace all attributes</option>
									<option value="replace_attr_custom">Replace selected attributes...</option>
									<option value="delete_attr">Delete selected attributes...</option>
									<option value="append">Append to existing parent</option>
									<option value="prepend">Prepend to existing parent</option>
									<option value="delete">Delete element</option>
								</select>
								<div class="siup_item_attributes_section">
									<select multiple class="siup_item_attributes"></select>
								</div>
							</td>
							<td>
								<a class="siup_item_delete" href="javascript:void(0)" onclick="SiupAdmin.pages.deleteInstruction(this)">&#215;</a>
							</td>
						</tr>
					</table>
					<div class="siup_buttons">
						<input type="button" class="button button-primary" onclick="SiupAdmin.pages.addInstruction()" value="Add Instruction" />
						<input type="button" class="button button-primary" onclick="SiupAdmin.pages.apply()" value="Apply Instructions" />
						<span class="siup_progress"></span>
					</div>
				</form>
				<script type="text/javascript">
					jQuery(function($){
					});
				</script>
			</div>
		</div>
	<?php
}

function siup_render_options_db(){
	?>
		<div class="wrap"
			<div class="siup_options">
				<h1>Rollout Tool</h1>
				<h3>Update Database Settings</h3>
				<h4>Copy database values for this site across the entire multisite.</h4>
				<form method="post" action="options.php">
					<div class="siup_source_table_section">
						Source database table:
						<select class="source_table" onchange="SiupAdmin.db.updateSource()">
							<?php
								global $wpdb;
								$selected_table = (!empty($_GET['table'])) ? $_GET['table'] : $wpdb->prefix . "options";
								$tables = $wpdb->get_results("SHOW TABLES FROM $wpdb->dbname LIKE '%$wpdb->prefix%'");
								foreach ($tables as $table) {
									foreach ($table as $t) {
										$selected = ($t == $selected_table) ? "selected" : "";
										echo "<option " . $selected . ">" . $t . "</option>";
									}
								}
							?>
						</select>
						&nbsp; &nbsp;
						Column name:
						<select class="source_table_column_name" onchange="SiupAdmin.db.updateSource()">
							<?php
								$selected_column_name = (!empty($_GET['column_name'])) ? $_GET['column_name'] : "option_name";
								$columns =  $wpdb->get_results("SHOW COLUMNS FROM " . $selected_table);
								foreach($columns as $column){
									$selected = ($column->Field == $selected_column_name) ? "selected" : "";
									echo "<option $selected value='$column->Field'>$column->Field</option>";
								}
							?>
						</select>
						&nbsp; &nbsp;
						Column value:
						<select class="source_table_column_value" onchange="SiupAdmin.db.updateSource()">
							<?php
								$selected_column_value = (!empty($_GET['column_value'])) ? $_GET['column_value'] : "option_value";
								foreach($columns as $column){
									$selected = ($column->Field == $selected_column_value) ? "selected" : "";
									echo "<option $selected value='$column->Field'>$column->Field</option>";
								}
							?>
						</select>
					</div>
					<div class="siup_target_sites_section">
						Target sites:
						<select multiple class="siup_target_sites">
							<?php
								$sites = wp_get_sites();
								foreach($sites as $site){
									if(
										$site['domain'] == 'exploremytown.com' || 
										$site['domain'] == 'template.exploremytown.com'
									) continue;

									?>
										<option value="<?=$site['blog_id'];?>" >
											<?=$site['domain'];?>
										</option>
									<?php
								}
							?>
						</select>
					</div>
					<table class="form-table siup_items">
						<tr class="siup_item template">
							<td>
								Setting Name: <br>
								<select class="siup_item_db_name" onchange="SiupAdmin.db.displaySetting(this)">
									<option disabled selected value="">Choose a setting</option>
									<?php 
										$options = $wpdb->get_results("SELECT * FROM $selected_table");
										foreach ($options as $option) {
											$option = (array)$option;
											$name = $option[$selected_column_name];
											$value = $option[$selected_column_value];

											$value_unserialized = @unserialize($value);
											$is_serialized = ($value_unserialized !== false) ? '1' : '0';
											$value_encoded = ($value_unserialized !== false) ? json_encode($value_unserialized, true) : $value;
											$value_encoded = rawurlencode($value_encoded);
											?>
												<option 
													value="<?=$name;?>"
													data-current-value="<?=$value_encoded;?>"
													data-is-serialized="<?=$is_serialized;?>"
												>
													<?=$name;?>
												</option>
											<?php
										}
									?>
								</select>
							</td>
							<td class="sub_setting_column">
								Sub-setting Name (for serialized values):<br>
								<select class="siup_item_db_sub" onchange="SiupAdmin.db.displaySubSetting(this)"></select>
							</td>
							<td>
								<a class="siup_item_delete" href="javascript:void(0)" onclick="SiupAdmin.pages.deleteInstruction(this)">&#215;</a>
							</td>
						</tr>
					</table>
					<div class="siup_buttons">
						<input type="button" class="button button-primary" onclick="SiupAdmin.pages.addInstruction()" value="Add Setting" />
						<input type="button" class="button button-primary" onclick="SiupAdmin.db.apply()" value="Apply Settings" />
						<span class="siup_progress"></span>
					</div>
				</form>
				<div class="siup_query">
					<h3>Update With SQL Query</h3>
					<h4>Use the fields below to run a database query on the tables selected above.</h4>
					Method: <select class="siup_query_method">
						<option value="query_update" selected>UPDATE</option>
						<option value="query_delete">DELETE</option>
					</select> &nbsp;
					Data: <input class="siup_query_data" type="text" size="50" value="" /> &nbsp;
					Where: <input class="siup_query_where" type="text" size="50" value="" /> &nbsp;
					<input type="button" class="button button-primary" onclick="SiupAdmin.db.runQuery()" value="Run Query" />
					<br><span class="siup_progress"></span>
				</div>
				<script type="text/javascript">
					jQuery(function($){
					});
				</script>
			</div>
		</div>
	<?php
}

function siup_render_options_clone(){
	?>
		<div class="wrap"
			<div class="siup_options">
				<h1>Rollout Tool</h1>
				<h3>Clone Database Entries</h3>
				<h4>Clone pages and other database entries to other sites.</h4>
				<form method="post" action="options.php">
					<div class="siup_source_table_section">
						Source database table:
						<select class="source_table" onchange="SiupAdmin.db.updateSource()">
							<?php
								global $wpdb;
								$selected_table = (!empty($_GET['table'])) ? $_GET['table'] : $wpdb->prefix . "posts";
								$tables = $wpdb->get_results("SHOW TABLES FROM $wpdb->dbname LIKE '%$wpdb->prefix%'");
								foreach ($tables as $table) {
									foreach ($table as $t) {
										$selected = ($t == $selected_table) ? "selected" : "";
										echo "<option " . $selected . ">" . $t . "</option>";
									}
								}
							?>
						</select>
						&nbsp; &nbsp;
						Column name:
						<select class="source_table_column_name" onchange="SiupAdmin.db.updateSource()">
							<?php
								$selected_column_name = (!empty($_GET['column_name'])) ? $_GET['column_name'] : "post_name";
								$columns =  $wpdb->get_results("SHOW COLUMNS FROM " . $selected_table);
								foreach($columns as $column){
									$selected = ($column->Field == $selected_column_name) ? "selected" : "";
									echo "<option $selected value='$column->Field'>$column->Field</option>";
								}
							?>
						</select>
						<input type="hidden" class="source_table_key_column_name" value="<?php 
							$keys = $wpdb->get_results("SHOW KEYS FROM " . $selected_table . " WHERE Key_name = 'PRIMARY'");
							echo($keys[0]->Column_name);
						?>" />
					</div>
					<div class="siup_target_sites_section">
						Target sites:
						<select multiple class="siup_target_sites">
							<?php
								$sites = wp_get_sites();
								foreach($sites as $site){
									if(
										$site['domain'] == 'exploremytown.com' || 
										$site['domain'] == 'template.exploremytown.com'
									) continue;

									?>
										<option value="<?=$site['blog_id'];?>" >
											<?=$site['domain'];?>
										</option>
									<?php
								}
							?>
						</select>
					</div>
					<table class="form-table siup_items">
						<tr class="siup_item template">
							<td>
								Action<br>
								<select class="siup_item_clone_action" onchange="SiupAdmin.clone.changeAction(this)">
									<option selected value="clone">Clone</option>
									<option value="delete">Delete</option>
								</select>
							</td>
							<td>
								Entry: <br>
								<select class="siup_item_clone_name_clone" onchange="">
									<option disabled selected value="">Choose a setting</option>
									<?php 
										$options = $wpdb->get_results("SELECT * FROM $selected_table");
										foreach ($options as $option) {
											$option 	= (array)$option;
											$name 		= $option[$selected_column_name];
											$type 		= (!empty($option['post_type'])) ? ' (' . $option['post_type'] . ')' : '';
											$id 		= reset($option);
											?>
												<option 
													value="<?=$name;?>"
													data-id="<?=$id;?>"
												>
													<?=$name;?> <?=$type;?>
												</option>
											<?php
										}
									?>
								</select>
								<input class="siup_item_clone_name_delete" onchange=""/>
							</td>
							<td>
								<a class="siup_item_delete" href="javascript:void(0)" onclick="SiupAdmin.pages.deleteInstruction(this)">&#215;</a>
							</td>
						</tr>
					</table>
					<div class="siup_buttons">
						<input type="button" class="button button-primary" onclick="SiupAdmin.pages.addInstruction()" value="Add Setting" />
						<input type="button" class="button button-primary" onclick="SiupAdmin.clone.apply()" value="Apply Settings" />
						<span class="siup_progress"></span>
					</div>
				</form>
				<script type="text/javascript">
					jQuery(function($){
					});
				</script>
			</div>
		</div>
	<?php
}

?>