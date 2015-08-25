<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class Gravity_Flow_Step_Notification extends Gravity_Flow_Step {
	public $_step_type = 'notification';

	public function get_label() {
		return esc_html__( 'Notification', 'gravityflow' );
	}

	public function get_settings(){

		$form = $this->get_form();
		$notfications = $form['notifications'];

		$choices = array();

		foreach ( $notfications as $notfication ) {
			$choices[] = array(
				'label' => $notfication['name'],
				'name' => 'notification_id_' . $notfication['id'],
			);
		}

		$account_choices = gravity_flow()->get_users_as_choices();

		return array(
			'title'  => 'Notification',
			'fields' => array(
				array(
					'name' => 'notification',
					'label' => esc_html__( 'Gravity Forms Notifications', 'gravityflow' ),
					'type' => 'checkbox',
					'required' => false,
					'choices' => $choices,
				),
				array(
					'name'    => 'workflow_notification_enabled',
					'label'   => __( 'Workflow notification', 'gravityflow' ),
					'tooltip'   => __( 'Enable this setting to send an email when the entry is rejected.', 'gravityflow' ),
					'type'    => 'checkbox',
					'choices' => array(
						array(
							'label'         => __( 'Enabled', 'gravityflow' ),
							'name'          => 'workflow_notification_enabled',
							'default_value' => false,
						),
					)
				),
				array(
					'name'    => 'workflow_notification_type',
					'label'   => __( 'Send To', 'gravityflow' ),
					'type'       => 'radio',
					'default_value' => 'select',
					'horizontal' => true,
					'choices'    => array(
						array( 'label' => __( 'Select Users', 'gravityflow' ), 'value' => 'select' ),
						array( 'label' => __( 'Configure Routing', 'gravityflow' ), 'value' => 'routing' ),
					),
				),
				array(
					'id'       => 'workflow_notification_users',
					'name'    => 'workflow_notification_users[]',
					'label'   => __( 'Select User', 'gravityflow' ),
					'size'     => '8',
					'multiple' => 'multiple',
					'type'     => 'select',
					'choices'  => $account_choices,
				),
				array(
					'name'  => 'workflow_notification_routing',
					'label' => __( 'Routing', 'gravityflow' ) ,
					'type'  => 'user_routing',
				),
				array(
					'name'  => 'workflow_notification_message',
					'label' => __( 'Message', 'gravityflow' ),
					'type'  => 'visual_editor',
				),
			),
		);
	}

	function process(){

		$entry = $this->get_entry();

		$form = $this->get_form();

		foreach ( $form['notifications'] as $notification ) {
			$notification_id = $notification['id'];
			$setting_key = 'notification_id_' . $notification_id;
			if ( $this->{$setting_key} ) {
				if ( ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $entry ) ) {
					$this->log_debug( __METHOD__ . "(): Notification conditional logic not met, not processing notification (#{$notification_id} - {$notification['name']})." );
					continue;
				}
				GFCommon::send_notification( $notification, $form, $entry );
				$note = sprintf( esc_html__( 'Sent Notification: %s' ), $notification['name'] );
				$this->add_note( $note );
			}
		}

		$this->send_workflow_notification();

		return true;
	}

	public function send_workflow_notification(){

		if ( ! $this->workflow_notification_enabled ) {
			return;
		}

		$assignees = array();

		$notification_type = $this->workflow_notification_type;

		switch ( $notification_type ) {
			case 'select' :
				if ( is_array( $this->workflow_notification_users ) ) {
					foreach ( $this->approval_notification_users as $assignee_key ) {
						$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
					}
				}
				break;
			case 'routing' :
				$routings = $this->workflow_notification_routing;
				if ( is_array( $routings ) ) {
					foreach ( $routings as $routing ) {
						if ( $user_is_assignee = $this->evaluate_routing_rule( $routing ) ) {
							$assignees[] = new Gravity_Flow_Assignee( rgar( $routing, 'assignee' ), $this );
						}
					}
				}

				break;
		}

		if ( empty( $assignees ) ) {
			return;
		}

		$body = $this->workflow_notification_message;

		$this->send_notifications( $assignees, $body );

		$note = esc_html__( 'Sent Notification' );
		$this->add_note( $note );

	}

}
Gravity_Flow_Steps::register( new Gravity_Flow_Step_Notification() );