<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}


class Gravity_Flow_Inbox {

	public static function display( $args ) {
		global $current_user;

		$defaults = array(
			'display_empty_fields' => true,
			'check_permissions'    => true,
			'form_id'              => 0,
			'detail_base_url'      => admin_url( 'admin.php?page=gravityflow-inbox&view=entry' ),
		);

		$args = array_merge( $defaults, $args );

		if ( $current_user->ID > 0 ) {
			$filter_key = 'user_id_' . $current_user->ID;
		} elseif ( $token = gravity_flow()->decode_access_token() ) {
			$filter_key = 'email_' . gravity_flow()->parse_token_assignee( $token )->get_id();
		}

		$entries = array();

		$form_id = 0;

		if ( ! empty( $filter_key ) ) {
			$field_filters[] = array(
				'key'   => 'workflow_' . $filter_key,
				'value' => 'pending',
			);
			$user_roles      = gravity_flow()->get_user_roles();
			foreach ( $user_roles as $user_role ) {
				$field_filters[] = array(
					'key'   => 'workflow_role_' . $user_role,
					'value' => 'pending',
				);
			}

			$field_filters['mode'] = 'any';

			$search_criteria['field_filters'] = $field_filters;
			$search_criteria['status']        = 'active';

			$form_ids = $args['form_id'] ? $args['form_id'] : gravity_flow()->get_workflow_form_ids();

			$entries = GFAPI::get_entries( $form_ids, $search_criteria );
		}

		if ( sizeof( $entries ) > 0 ) {
			?>

			<table id="gravityflow-inbox" class="widefat" cellspacing="0" style="border:0px;">
				<thead>
				<tr>
					<th data-label="<?php esc_html_e( 'ID', 'gravityflow' ); ?>"><?php esc_html_e( 'ID', 'gravityflow' ); ?></th>
					<th><?php esc_html_e( 'Form', 'gravityflow' ); ?></th>
					<th><?php esc_html_e( 'Submitted by', 'gravityflow' ); ?></th>
					<th><?php esc_html_e( 'Step', 'gravityflow' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'gravityflow' ); ?></th>
					<?php
					if ( $args['form_id'] ) {
						$form = GFAPI::get_form( $args['form_id'] );
						if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
							foreach ( $form['fields'] as $field ) {
								/* @var GF_Field $field */
								$label = GFCommon::get_label( $field );
								echo '<th>' . esc_html( $label ) . '</th>';
							}
						}
					}

					?>
				</tr>
				</thead>

				<tbody class="list:user user-list">
				<?php
				foreach ( $entries as $entry ) {
					$form      = GFAPI::get_form( $entry['form_id'] );
					$user      = get_user_by( 'id', (int) $entry['created_by'] );
					$name      = $user ? $user->display_name : $entry['ip'];
					$base_url  = $args['detail_base_url'];
					$url_entry = $base_url . sprintf( '&id=%d&lid=%d', $entry['form_id'], $entry['id'] );
					$url_entry = esc_url_raw( $url_entry );
					$link      = "<a href='%s'>%s</a>";
					?>
					<tr>
						<td data-label="<?php esc_html_e( 'ID', 'gravityflow' ); ?>">
							<?php
							printf( $link, $url_entry, $entry['id'] );
							?>
						</td>
						<td data-label="<?php esc_html_e( 'Form', 'gravityflow' ); ?>">
							<?php
							printf( $link, $url_entry, $form['title'] );
							?>
						</td>
						<td data-label="<?php esc_html_e( 'Submitted by', 'gravityflow' ); ?>">
							<?php
							printf( $link, $url_entry, $name );

							?>
						</td>
						<td data-label="<?php esc_html_e( 'Step', 'gravityflow' ); ?>">
							<?php
							if ( isset( $entry['workflow_step'] ) ) {
								$step = gravity_flow()->get_step( $entry['workflow_step'] );
								if ( $step ) {
									printf( $link, $url_entry, $step->get_name() );
								}
							}

							?>
						</td>
						<td data-label="<?php esc_html_e( 'Submitted', 'gravityflow' ); ?>">
							<?php

							printf( $link, $url_entry, GFCommon::format_date( $entry['date_created'] ) );
							?>
						</td>

						<?php
						if ( $args['form_id'] ) {
							if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
								$columns = RGFormsModel::get_grid_columns( $form_id, true );
								foreach ( $form['fields'] as $field ) {
									/* @var GF_Field $field */
									?>
									<td data-label="<?php echo esc_attr( GFCommon::get_label( $field ) ); ?>">
										<?php echo $field->get_value_entry_list( rgar( $entry, $field->id ), $entry, $field->id, $columns, $form ); ?>
									</td>
								<?php
								}
							}
						}

						?>
					</tr>
				<?php
				}
				?>
				</tbody>
			</table>

		<?php
		} else {
			?>
			<div id="gravityflow-no-pending-tasks-container">
				<div id="gravityflow-no-pending-tasks-content">
					<i class="fa fa-check-circle-o gravityflow-inbox-check"></i>
					<br/><br/>
					<?php esc_html_e( "No pending tasks", 'gravityflow' ); ?>
				</div>

			</div>
		<?php
		}
	}
}