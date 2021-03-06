<?php
/* 	
 * Admin methods for managing, viewing, and creating manual payment records.
 *
*/

// begin add boxes
add_action( 'add_meta_boxes', 'mt_add_meta_boxes' );
function mt_add_meta_boxes() {
	add_meta_box( 'mt_purchase_options', __( 'Purchase Options', 'my-tickets' ), 'mt_add_inner_box', 'mt-payments', 'normal', 'high' );
	add_meta_box( 'mt_purchase_info', __( 'Purchase Information', 'my-tickets' ), 'mt_add_uneditable', 'mt-payments', 'side', 'high' );
	if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) ) {
		global $post_id;
		add_meta_box( 'mt_send_email', __( 'Contact Purchaser', 'my-tickets' ), 'mt_email_purchaser', 'mt-payments', 'normal', 'default' );
		if ( get_post_meta( $post_id, '_error_log', true ) != '' && current_user_can( 'manage_options' ) ) {
			add_meta_box( 'mt_error_log', __( 'Error Data', 'my-tickets' ), 'mt_error_data', 'mt-payments', 'normal', 'default' );
		}
	}
}


function mt_error_data() {
	global $post_id;
	echo "<pre>";
	print_r( get_post_meta( $post_id, '_error_log' ) );
	echo "</pre>";
}


function mt_email_purchaser() {
	global $post_id;
	$messages = false;
	$nonce   = '<input type="hidden" name="mt-email-nonce" value="' . wp_create_nonce( 'mt-email-nonce' ) . '" />';
	$form    = "<p><label for='mt_send_subject'>" . __( 'Subject', 'my-tickets' ) . "</label><br /><input type='text' size='60' name='mt_send_subject' id='mt_send_subject' /></p>
	<p><label for='mt_send_email'>" . __( 'Message', 'my-tickets' ) . "</label><br /><textarea cols='60' rows='6' name='mt_send_email' id='mt_send_email'></textarea></p>
	<input type='submit' class='button-primary' id='mt_email_form' value='" . __( 'Email Purchaser', 'my-tickets' ) . "' />";
	$email   = get_post_meta( $post_id, '_mt_send_email' );
	$message = "<h4>" . __( 'Prior Messages', 'my-tickets' ) . "</h4>";
	foreach ( $email as $mail ) {
		if ( is_array( $mail ) ) {
			$body    = $mail['body'];
			$subject = $mail['subject'];
			$date    = date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $mail['date'] );
			$message .= "<li><strong>$subject</strong> ($date)<br /><blockquote>" . stripslashes( esc_html( $body ) ) . "</blockquote>";
			$messages = true;
		}
	}
	$prior = ( $messages ) ? "<ul>".$message."</ul>" : '';
	echo '<div class="mt_post_fields panels">' . $nonce . $form . $prior . '</div>';
}

add_action( 'save_post', 'mt_delete_error_log', 10 );
function mt_delete_error_log( $id ) {
	if ( isset( $_POST['mt_delete_log'] ) ) {
		mt_delete_log( $id );
	}
}

add_action( 'save_post', 'mt_cpt_email_purchaser', 10 );
function mt_cpt_email_purchaser( $id ) {
	if ( isset( $_POST['mt-email-nonce'] ) ) {
		$options  = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		$blogname = get_option( 'blogname' );
		$nonce    = $_POST['mt-email-nonce'];
		if ( ! wp_verify_nonce( $nonce, 'mt-email-nonce' ) ) {
			wp_die( "Invalid nonce" );
		}
		if ( isset( $_POST['_inline_edit'] ) ) {
			return;
		}

		if ( isset( $_POST['mt_send_email'] ) && $_POST['mt_send_email'] != '' ) {
			$body        = stripslashes( $_POST['mt_send_email'] );
			$subject     = stripslashes( $_POST['mt_send_subject'] );
			$email       = get_post_meta( $id, '_email', true );
			$opt_out_url = add_query_arg();
			$opt_out     = PHP_EOL . PHP_EOL . "<p><small>" . sprintf( __( "Don't want to receive email from us? Follow this link: %s", 'my-tickets' ), $opt_out_url ) . "</small></p>";
			$headers[]   = "From: $blogname Events <" . $options['mt_from'] . ">";
			$headers[]   = "Reply-to: $options[mt_from]";
			if ( $options['mt_html_email'] == 'true' ) {
				add_filter( 'wp_mail_content_type', create_function( '', 'return "text/html";' ) );
				$body = wpautop( $body . $opt_out );
			} else {
				$body = strip_tags( $body . $opt_out );
			}
			$body = apply_filters( 'mt_modify_email_body', $body );

			// message to purchaser
			wp_mail( $email, $subject, $body, $headers );

			if ( $options['mt_html_email'] == 'true' ) {
				remove_filter( 'wp_mail_content_type', create_function( '', 'return "text/html";' ) );
			}
			add_post_meta( $id, '_mt_send_email', array(
					'body'    => $body,
					'subject' => $subject,
					'date'    => current_time( 'timestamp' )
				) );
		}
	}
}

function mt_default_fields() {
	$mt_fields =
		array(
			'is_paid'           => array(
				'label'   => __( 'Payment Status', 'my-tickets' ),
				'input'   => 'select',
				'default' => 'Pending',
				'choices' => array( '--', 'Completed', 'Pending', 'Failed', 'Refunded', 'Other' )
			),
			'ticketing_method'  => array(
				'label'   => __( 'Ticketing Method', 'my-tickets' ),
				'input'   => 'select',
				'default' => 'willcall',
				'choices' => apply_filters( 'mt_registration_tickets_options', array(
						'printable' => __( 'Printable', 'my-tickets' ),
						'eticket'   => __( 'E-tickets', 'my-tickets' ),
						'postal'    => __( 'Postal Mail', 'my-tickets' ),
						'willcall'  => __( 'Pick up at box office', 'my-tickets' )
					) )
			),
			'is_delivered'         => array(
				'label'   => __( 'Ticket Delivered', 'my-tickets' ),
				'input'   => 'checkbox',
				'default' => '',
				'notes'   => __( 'E-tickets and printable tickets are delivered via email.', 'my-tickets' )
			),
			'mt_return_tickets' => array(
				'label'   => __( 'Return tickets to purchase pool', 'my-tickets' ),
				'input'   => 'checkbox',
				'default' => 'checked'
			),
			'total_paid'        => array(
				'label'   => __( 'Tickets Total', 'my-tickets' ),
				'input'   => 'text',
				'default' => ''
			),
			'email'             => array(
				'label'   => __( 'Purchaser Email', 'my-tickets' ),
				'input'   => 'text',
				'default' => ''
			),
			'phone'             => array(
				'label' => __( 'Purchaser Phone', 'my-tickets' ),
				'input' => 'text',
				'default' => ''
			),
			'notes'             => array(
				'label' => __( 'Payment Notes', 'my-tickets' ),
				'input' => 'textarea',
				'notes' => 'Internal-use only'
			),
			'send_email'        => array(
				'label'   => __( 'Re-send Email Notification', 'my-tickets' ),
				'input'   => 'checkbox',
				'context' => 'edit'
			),
			'gateway'           => array(
				'label'   => __( 'Payment Method', 'my-tickets' ),
				'input'   => 'select',
				'choices' => array( 'Credit Card', 'Check', 'Cash', 'Other' ),
				'context' => 'new'
			),
			'transaction_id'    => array(
				'label'   => __( 'Transaction ID', 'my-tickets' ),
				'input'   => 'text',
				'context' => 'new'
			),
		);

	return apply_filters( 'mt_add_custom_fields', $mt_fields );
}

function mt_add_inner_box() {
	global $post_id;
	$fields = mt_default_fields();
	$format = sprintf(
		'<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />',
		'mt-meta-nonce', wp_create_nonce( 'mt-meta-nonce' )
	);
	foreach ( $fields as $key => $value ) {
		$label    = $value['label'];
		$input    = $value['input'];
		$choices  = ( isset( $value['choices'] ) ) ? $value['choices'] : false;
		$multiple = ( isset( $value['multiple'] ) ) ? true : false;
		$notes    = ( isset( $value['notes'] ) ) ? $value['notes'] : '';
		$format .= mt_create_field( $key, $label, $input, $post_id, $choices, $multiple, $notes, $value );
	}
	if ( ! isset( $_GET['post'] ) ) {
		// for new payments only; imports user's cart
		$cart_id = $cart_transient_id = false;
		if ( isset( $_GET['cart'] ) && is_numeric( $_GET['cart'] ) ) {
			$cart_id = (int) $_GET['cart'];
		}
		if ( isset( $_GET['cart_id'] ) ) {
			$cart_transient_id = esc_sql( $_GET['cart_id'] );
		}
		$cart  = mt_get_cart( $cart_id, $cart_transient_id );
		$order = ( $cart ) ? mt_generate_cart_table( $cart, 'confirmation' ) : "<p>" . sprintf( __( 'Visit the <a href="%s">public web site</a> to set up a cart order', 'my-tickets' ), home_url() ) . "</p>";
		$total = "<strong>" . __( 'Total', 'my-tickets' ) . "</strong>: " . apply_filters( 'mt_money_format', mt_total_cart( $cart ) );
	} else {
		$order = $total = '';
	}
	echo '<div class="mt_post_fields">' . $format . $order . $total . '</div>';
}

/**
 * Create interface for viewing payment fields that can't be edited.
 */
function mt_add_uneditable() {
	global $post_id;
	if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) ) {
		$receipt       = get_post_meta( $post_id, '_receipt', true );
		$options  = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		$link    = add_query_arg( 'receipt_id', $receipt, get_permalink( $options['mt_receipt_page'] ) );
		$purchase      = get_post_meta( $post_id, '_purchased' );
		$discount      = get_post_meta( $post_id, '_discount', true );
		$discount_text = ( $discount != '' ) ? sprintf( __( " @ %d&#37; member discount", 'my-tickets' ), $discount ) : '';

		$tickets          = mt_setup_tickets( $purchase, $post_id );
		$ticket_data      = "<div class='ticket-data panel'><div class='inner'><h4>" . __( 'Tickets', 'my-tickets' ) . "</h4>" . mt_format_tickets( $tickets, 'html', $post_id ) . "</div></div>";
		$purchase_data    = "<div class='transaction-purchase panel'><div class='inner'><h4>" . __( 'Receipt ID:', 'my-tickets' ) . " <code><a href='$link'>$receipt</a></code></h4>" . mt_format_purchase( $purchase, 'html', $post_id ) . "</div></div>";
		$gateway          = get_post_meta( $post_id, '_gateway', true );
		$transaction_data = "<div class='transaction-data $gateway panel'><div class='inner'><h4>" . __( 'Paid through:', 'my-tickets' ) . " <code>$gateway</code>$discount_text</h4>" . apply_filters( 'mt_format_transaction', get_post_meta( $post_id, '_transaction_data', true ), get_post_meta( $post_id, '_gateway', true ) ) . "</div></div>";

		$other_data = apply_filters( 'mt_show_in_payment_fields', '', $post_id );
		if ( $other_data !== '' ) {
			$other_data = "<div class='custom-data panel'><div class='inner'><h4>" . __( 'Custom Field Data', 'my-tickets' ) . "</h4>" . $other_data . "</div></div>";
		}
		echo '<div class="mt_post_fields panels">' . $transaction_data . $purchase_data . $ticket_data . $other_data . '</div>';
	}
}

/**
 * Get a list of event IDs for any given purchase.
 *
 * @param $purchase_id
 *
 * @return array
 */
function mt_list_events( $purchase_id ) {
	$purchase = get_post_meta( $purchase_id, '_purchased' );
	$events = array();
	if ( is_array( $purchase ) ) {
		foreach ( $purchase as $purch ) {
			foreach ( $purch as $event => $tickets ) {
				$events[] = $event;
			}
		}
	}

	return $events;
}

/**
 * Generate tickets for a given purchase.
 *
 * @param $purchase
 * @param $id
 *
 * @return array
 */
function mt_setup_tickets( $purchase, $id ) {
	$options      = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
	$ticket_array = array();
	foreach ( $purchase as $purch ) {
		foreach ( $purch as $event => $tickets ) {
			$purchases[ $event ] = $tickets;
			foreach ( $tickets as $type => $details ) {
				// add ticket hash for each ticket
				$count = $details['count'];
				// only add tickets if count of tickets is more than 0
				if ( $count >= 1 ) {
					$price = $details['price'];
					for ( $i = 0; $i < $count; $i ++ ) {
						$ticket_id = mt_generate_ticket_id( $id, $event, $type, $i, $price );
						// check for existing ticket data
						$meta        = get_post_meta( $id, $ticket_id, true );
						$ticket_meta = get_post_meta( $event, '_ticket' );
						// if ticket data doesn't exist, create it.
						if ( ! $meta ) {
							if ( ! in_array( $ticket_id, $ticket_meta ) ) {
								add_post_meta( $event, '_ticket', $ticket_id );
							}
							update_post_meta( $id, $ticket_id, array(
								'type'        => $type,
								'price'       => $price,
								'purchase_id' => $id
							) );
						}

						$ticket_array[] = add_query_arg( 'ticket_id', $ticket_id, get_permalink( $options['mt_tickets_page'] ) );
					}
				}
			}
		}
	}

	return $ticket_array;
}

add_filter( 'mt_format_transaction', 'mt_offline_transaction', 5, 2 );
/**
 * Format transaction data shown in Payment history.
 *
 * @param $transaction
 * @param $gateway
 *
 * @return string
 */
function mt_offline_transaction( $transaction, $gateway ) {
	// this is the default format. 
	$output = $shipping = '';
	if ( is_array( $transaction ) ) {
		foreach ( $transaction as $key => $value ) {
			if ( $key == 'shipping' ) {
				foreach ( $value as $label => $field ) {
					$shipping .= "<li><strong>" . ucfirst( $label ) . "</strong> $field</li>";
				}
			} else {
				$output .= "<li><strong>" . ucfirst( str_replace( '_', ' ', $key ) ) . "</strong>: $value</li>";
			}
		}
	}
	if ( ! $output ) {
		return __( "Transaction not yet completed.", 'my-tickets' );
	} else {
		if ( $shipping ) {
			$shipping = "<h4>" . __( 'Shipping Address', 'my-tickets' ) . "</h4><ul>" . $shipping . "</ul>";
		}
		if ( $output ) {
			$output = "<ul>" . $output . "</ul>";
		}

		return $output . $shipping;
	}
}

/**
 * Define meta box fields that can be changed by Admin in a payment record.
 *
 * @param $key
 * @param $label
 * @param $type
 * @param $post_id
 * @param bool $choices
 * @param bool $multiple
 * @param string $notes
 * @param $field
 *
 * @return bool|string
 */
function mt_create_field( $key, $label, $type, $post_id, $choices = false, $multiple = false, $notes = '', $field ) {
	$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
	if ( isset( $field['context'] ) && $field['context'] == 'edit' && ! isset( $_GET['post'] ) ) {
		return '';
	}
	if ( isset( $field['context'] ) && $field['context'] == 'new' && isset( $_GET['post'] ) ) {
		return '';
	}
	$value = false;
	if ( $multiple == 'true' ) {
		$custom = (array) get_post_meta( $post_id, "_" . $key );
	} else {
		$custom = esc_attr( get_post_meta( $post_id, "_" . $key, true ) );
	}
	if ( $key != 'notes' && get_post_meta( $post_id, '_is_paid', true ) == 'Refunded' ) {
		$disabled = 'disabled';
	} else {
		$disabled = '';
	}
	switch ( $type ) {
		case 'text':
			if ( $multiple ) {
				foreach ( $custom as $val ) {
					if ( is_array( $val ) ) {
						foreach ( $val as $event => $tickets ) {
							$event_title = get_the_title( $event );
							$value .= '<div><p><strong>' . $label . ': ' . $event_title . '</strong><br />';
							foreach ( $tickets as $klabel => $data ) {
								$value .= "<em>$klabel</em>: $data[count] @ $data[price]<br />";
							}
							$value .= "</p></div>";
						}
					}
				}
			} else {
				if ( $key == 'total_paid' && $custom != '' ) {
					$custom = money_format( '%i', $custom );
					$label .= " (" . $options['mt_currency'] . ")";
				}
				$value = "
				<div>
					<label for='_$key'>$label</label><br />
					<input class='widefat' type='text' name='_$key' id='$key' value='$custom' $disabled />
				</div>";
			}
			break;
		case 'textarea':
			$value = '
			<div>
				<label for="_' . $key . '">' . $label . ' <em>(' . $notes . ')</em></label><br />' .
			         '<textarea class="widefat" cols="60" rows="4" name="_' . $key . '" id="_' . $key . '">' . $custom . '</textarea>
			</div>';
			break;
		case 'checkbox':
			// the mt_return_tickets should only be visible if a payment is failed.
			if ( ( $key == 'mt_return_tickets' && get_post_meta( $post_id, '_is_paid', true ) == 'Failed' || get_post_meta( $post_id, '_is_paid', true ) == 'Refunded' ) || $key != 'mt_return_tickets' ) {
				if ( $key == 'mt_return_tickets' && get_post_meta( $post_id, '_returned', true ) == 'true' ) {
					$notes = __( 'Tickets from this purchase have been returned to the purchase pool', 'my-tickets' );
				}
				$checked = checked( $custom, 'true', false );
				$value = '
				<div>
					<input type="checkbox" name="_' . $key . '" id="_' . $key . '" aria-labelledby="_' . $key . ' _' . $key . '_notes" value="true" ' . $checked . ' /> <label for="_' . $key . '">' . $label . '</label><br />
					<span id="_' . $key . '_notes">' . $notes . '</span>
				</div>';
			}
			break;
		case 'select':
			$value = '
		<div>
			<label for="_' . $key . '">' . $label . '</label> ' .
			         '<select name="_' . $key . '" id="_' . $key . '">' .
			         mt_create_options( $choices, $custom ) .
			         '</select>
		</div>';
			break;
		case 'none':
			$value = "
		<div>
			<p><strong>$label</strong>: <span>" . esc_html( $custom ) . "</span></p>
		</div>";
			break;
	}

	return $value;
}

/**
 * Create options for a custom select control.
 *
 * @param $choices
 * @param $selected
 *
 * @return string
 */
function mt_create_options( $choices, $selected ) {
	$return = '';
	if ( is_array( $choices ) ) {
		foreach ( $choices as $key => $value ) {
			if ( ! is_numeric( $key ) ) {
				$k      = esc_attr( $key );
				$chosen = ( $k == $selected ) ? ' selected="selected"' : '';
				$return .= "<option value='$key'$chosen>$value</option>";
			} else {
				$v      = esc_attr( $value );
				$chosen = ( $v == $selected ) ? ' selected="selected"' : '';
				$return .= "<option value='$value'$chosen>$value</option>";
			}
		}
	}

	return $return;
}

add_action( 'save_post', 'mt_post_meta', 10 );
/**
 * Save updates to payment meta data
 *
 * @param $id
 */
function mt_post_meta( $id ) {
	$fields = mt_default_fields();
	if ( isset( $_POST['mt-meta-nonce'] ) ) {
		$nonce = $_POST['mt-meta-nonce'];
		if ( ! wp_verify_nonce( $nonce, 'mt-meta-nonce' ) ) {
			wp_die( "Invalid nonce" );
		}
		if ( isset( $_POST['_inline_edit'] ) ) {
			return;
		}
		// create new ticket purchase
		if ( isset( $_POST['mt_cart_order'] ) ) {
			$purchased = $_POST['mt_cart_order'];
			mt_create_tickets( $id, $purchased );
			$receipt_id = md5( get_permalink( $id ) );
			update_post_meta( $id, '_receipt', $receipt_id );
		}

		if ( is_array( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				if ( isset( $_POST[ "_" . $key ] ) ) {
					$value = $_POST[ "_" . $key ];
					update_post_meta( $id, "_" . $key, $value );
					if ( $key == 'mt_return_tickets' && $value == 'true' ) {
						mt_return_tickets( $id );
					}
				}
			}
		}
	}
}

add_action( 'init', 'mt_posttypes' );
/**
 * Define Payments post type.
 */
function mt_posttypes() {
	$labels = array(
		'name'               => 'Payments',
		'singular_name'      => 'Payment',
		'menu_name'          => 'Payments',
		'add_new'            => __( 'Add New', 'my-tickets' ),
		'add_new_item'       => __( 'Create New Payment', 'my-tickets' ),
		'edit_item'          => __( 'Modify Payment', 'my-tickets' ),
		'new_item'           => __( 'New Payment', 'my-tickets' ),
		'view_item'          => __( 'View Payment', 'my-tickets' ),
		'search_items'       => __( 'Search payments', 'my-tickets' ),
		'not_found'          => __( 'No payments found', 'my-tickets' ),
		'not_found_in_trash' => __( 'No payments found in Trash', 'my-tickets' ),
		'parent_item_colon'  => ''
	);
	$args   = array(
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-tickets',
		'query_var'           => true,
		'hierarchical'        => false,
		'supports'            => array( 'title' )
	);
	register_post_type( 'mt-payments', $args );
}

add_filter( 'post_updated_messages', 'mt_posttypes_messages' );
/**
 * Define textdomain messages for Payments post type.
 *
 * @param $messages
 *
 * @return mixed
 */
function mt_posttypes_messages( $messages ) {
	global $post;
	$messages['mt-payments'] = array(
		0  => '', // Unused. Messages start at index 1.
		1  => __( 'Payment updated.', 'my-tickets' ),
		2  => __( 'Custom field updated.', 'my-tickets' ),
		3  => __( 'Custom field deleted.', 'my-tickets' ),
		4  => __( 'Payment updated.', 'my-tickets' ),
		/* translators: %s: date and time of the revision */
		5  => isset( $_GET['revision'] ) ? sprintf( __( 'Payment restored to revision from %s', 'my-tickets' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => __( 'Payment published.', 'my-tickets' ),
		7  => __( 'Payment saved.', 'my-tickets' ),
		8  => __( 'Payment submitted.', 'my-tickets' ),
		9  => sprintf( __( 'Payment scheduled for: <strong>%s</strong>.', 'my-tickets' ), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) ),
		10 => __( 'Payment draft updated.', 'my-tickets' ),
	);

	return $messages;
}

/**
 * Get value of a custom field by fieldname and post ID.
 *
 * @param $field
 * @param string $id
 *
 * @return mixed
 */
function mt_get_custom_field( $field, $id = '' ) {
	global $post;
	$id           = ( $id != '' ) ? $id : $post->ID;
	$custom_field = get_post_meta( $id, $field, true );

	return $custom_field;
}

// Actions/Filters for various tables and the css output
add_action( 'admin_init', 'mt_add' );
/**
 * Add custom columns to payments post type page.
 */
function mt_add() {
	$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
	add_action( 'admin_head', 'mt_css' );
	add_filter( "manage_mt-payments_posts_columns", 'mt_column' );
	add_action( "manage_mt-payments_posts_custom_column", 'mt_custom_column', 10, 2 );
	foreach ( $options['mt_post_types'] as $name ) {
		add_filter( "manage_" . $name . "_posts_columns", 'mt_is_event' );
		add_action( "manage_" . $name . "_posts_custom_column", 'mt_is_event_column', 10, 2 );
	}
}

/**
 * Add column to show whether a post has event characteristics to post manager.
 *
 * @param $cols
 *
 * @return mixed
 */
function mt_is_event( $cols ) {
	$cols['mt_is_event'] = __( 'Tickets', 'my-ticket' );

	return $cols;
}

/**
 * Add status/total and receipt ID fields to Payments post type.
 *
 * @param $cols
 *
 * @return mixed
 */
function mt_column( $cols ) {
	$cols['mt_status']  = __( 'Status', 'my-tickets' );
	$cols['mt_paid']    = __( 'Cart Total', 'my-tickets' );
	$cols['mt_receipt'] = __( 'Receipt ID', 'my-tickets' );

	return $cols;
}

/**
 * If post object has event characteristics, show tickets sold/remaining.
 *
 * @param $column_name
 * @param $id
 */
function mt_is_event_column( $column_name, $id ) {
	switch ( $column_name ) {
		case 'mt_is_event' :
			$event_data = get_post_meta( $id, '_mc_event_data', true );
			if ( $event_data ) {
				$registration = get_post_meta( $id, '_mt_registration_options', true );
				if ( is_array( $registration ) ) {
					$available    = $registration['total'];
					$pricing      = $registration['prices'];
					$tickets      = mt_tickets_left( $pricing, $available );
					$remain       = $tickets['remain'];
					$sold         = $tickets['sold'];
					$status       = "<span class='mt is-event'>" . sprintf( __( '%1$s (%2$s sold)', 'my-tickets' ), $remain, $sold ) . "</span>";
				} else {
					$status       = "<span class='mt not-event'>" . __( 'Not ticketed', 'my-tickets' ) . "</span>";
				}
			} else {
				$status = "<span class='mt not-event'>" . __( 'Not ticketed', 'my-tickets' ) . "</span>";
			}
			echo $status;
			break;
	}
}

// Echo the ID for the new column
/**
 * In Payment post type, get status paid and receipt data.
 * @param $column_name
 * @param $id
 */
function mt_custom_column( $column_name, $id ) {
	switch ( $column_name ) {
		case 'mt_status' :
			$pd       = get_post_meta( $id, '_is_paid', true );
			$pd_class = esc_attr( strtolower( $pd ) );
			$pd_class = ( strpos( $pd_class, 'other' ) !== false ) ? 'other' : $pd_class;
			$status   = "<span class='mt $pd_class'>$pd</span>";
			echo $status;
			break;
		case 'mt_paid' :
			$pd   = get_post_meta( $id, '_total_paid', true );
			$pd   = apply_filters( 'mt_money_format', $pd );
			$paid = "<span>$pd</span>";
			echo $paid;
			break;
		case 'mt_receipt' :
			$pd      = get_post_meta( $id, '_receipt', true );
			$receipt = "<code>$pd</code>";
			echo $receipt;
			break;
	}
}

function mt_return_value( $value, $column_name, $id ) {
	if ( $column_name == 'mt_status' || $column_name == 'mt_paid' || $column_name == 'mt_receipt' ) {
		$value = $id;
	}

	return $value;
}

// Output CSS for width of new column
function mt_css() {
	global $current_screen;
	if ( $current_screen->id == 'mt-payments' || $current_screen->id == 'edit-mt-payments' ) {
		wp_enqueue_style( 'mt.posts', plugins_url( 'css/mt-post.css', __FILE__ ) );
	}
}

add_filter( "pre_get_posts", 'filter_mt_payments' );
function filter_mt_payments( $query ) {
	global $pagenow;
	if ( ! is_admin() ) {
		return;
	}

	$qv = &$query->query_vars;
	if ( $pagenow == 'edit.php' && ! empty( $qv['post_type'] ) && $qv['post_type'] == 'mt-payments' ) {
		if ( empty( $_GET['mt_filter'] ) || $_GET['mt_filter'] == 'all' ) {
			return;
		}
		if ( isset( $_GET['mt_filter'] ) ) {
			$value = esc_sql( $_GET['mt_filter'] );
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => '_is_paid',
						'value'   => $value,
						'compare' => '='
					)
				)
			);
		}
	}
}

add_action( "restrict_manage_posts", 'filter_mt_dropdown' );
function filter_mt_dropdown() {
	global $typenow;
	if ( $typenow == 'mt-payments' ) {
		if ( isset( $_GET['mt_filter'] ) ) {
			$completed = ( $_GET['mt_filter'] == 'Completed' ) ? ' selected="selected"' : '';
			$pending   = ( $_GET['mt_filter'] == 'Pending' ) ? ' selected="selected"' : '';
			$refunded  = ( $_GET['mt_filter'] == 'Refunded' ) ? ' selected="selected"' : '';
			$failed    = ( $_GET['mt_filter'] == 'Failed' ) ? ' selected="selected"' : '';
		} else {
			$completed = $pending = $refunded = $failed = '';
		}
		?>
		<label for="mt_filter" class="screen-reader-text"><?php _e( 'Filter Payments', 'my-tickets' ); ?></label>
		<select class="postform" id="mt_filter" name="mt_filter">
			<option value="all"><?php _e( 'All Payments', 'my-tickets' ); ?></option>
			<option value="Completed"<?php echo $completed; ?>><?php _e( 'Completed', 'my-tickets' ); ?></option>
			<option value="Pending"<?php echo $pending; ?>><?php _e( 'Pending', 'my-tickets' ); ?></option>
			<option value="Refunded"<?php echo $refunded; ?>><?php _e( 'Refunded', 'my-tickets' ); ?></option>
			<option value="Failed"<?php echo $failed; ?>><?php _e( 'Failed', 'my-tickets' ); ?></option>
		</select>
	<?php
	}
}

/* Add bulk action to mark payments completed. */
add_action( 'admin_footer-edit.php', 'mt_bulk_admin_footer' );
function mt_bulk_admin_footer() {
	global $post_type;
	if ( $post_type == 'mt-payments' ) {
		?>
		<script>
			jQuery(document).ready(function ($) {
				$('<option>').val('complete').text('<?php _e( 'Mark as Completed', 'my-tickets' )?>').appendTo("select[name='action']");
			});
		</script>
	<?php
	}
}

add_action( 'load-edit.php', 'mt_bulk_action' );
function mt_bulk_action() {
	global $typenow;
	$post_type = $typenow;

	if ( $post_type == 'mt-payments' ) {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		$allowed_actions = array( 'complete' );
		if ( ! in_array( $action, $allowed_actions ) ) {
			return;
		}

		// security check
		check_admin_referer( 'bulk-posts' );

		if ( isset( $_REQUEST['post'] ) ) {
			$post_ids = array_map( 'intval', $_REQUEST['post'] );
		}

		if ( empty( $post_ids ) ) {
			return;
		}

		$sendback = remove_query_arg( array( 'completed', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );
		if ( ! $sendback ) {
			$sendback = admin_url( "edit.php?post_type=$post_type" );
		}

		$pagenum  = $wp_list_table->get_pagenum();
		$sendback = add_query_arg( 'paged', $pagenum, $sendback );

		switch ( $action ) {
			case 'complete':
				$completed = 0;
				foreach ( $post_ids as $post_id ) {
					update_post_meta( $post_id, '_is_paid', 'Completed' );
					wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
					$completed ++;
				}
				// build the redirect url
				$sendback = esc_url( add_query_arg( array(
						'completed' => $completed,
						'ids'       => join( ',', $post_ids )
					), $sendback ) );
				break;
			default:
				return;
		}

		$sendback = esc_url( remove_query_arg( array(
				'action',
				'action2',
				'tags_input',
				'post_author',
				'comment_status',
				'ping_status',
				'_status',
				'post',
				'bulk_edit',
				'post_view'
			), $sendback ) );

		wp_redirect( $sendback );
		exit();
	}
}

add_action( 'admin_notices', 'mt_bulk_admin_notices' );
function mt_bulk_admin_notices() {
	global $post_type, $pagenow;
	if ( $pagenow == 'edit.php' && $post_type == 'mt-payments' && isset( $_REQUEST['completed'] ) && (int) $_REQUEST['completed'] ) {
		$message = sprintf( _n( 'Payment completed & ticket notification sent.', '%s payments completed and ticket notifications sent.', $_REQUEST['completed'], 'my-tickets' ), number_format_i18n( $_REQUEST['completed'] ) );
		echo "<div class='updated'><p>$message</p></div>";
	}
}

add_filter( 'wp_list_pages_excludes', 'mt_exclude_pages', 10, 2 );
/**
 * Exclude receipt and ticket pages from page lists.
 *
 * @param $array
 *
 * @return array
 */
function mt_exclude_pages( $array ) {
	if ( !is_admin() ) {
		$options  = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		$tickets  = $options['mt_tickets_page'];
		$receipts = $options['mt_receipt_page'];
		if ( $tickets && $receipts ) {
			$array[] = $tickets;
			$array[] = $receipts;
		}
	}
	return $array;
}

add_filter( 'display_post_states', 'mt_post_states', 10, 2 );
/**
 * Change 'draft' label to 'Active cart'
 *
 * @param $post_states Default post states array
 * @param $post Current post
 *
 * @return array
 */
function mt_post_states( $post_states, $post ) {
	$post = get_post( $post );
	if ( get_post_type( $post ) == 'mt-payments' && $post->post_status == 'draft' ) {
		$post_states['draft'] = __( 'Active cart', 'my-tickets' );
	}
	return $post_states;
}