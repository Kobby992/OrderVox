<?php
/**
 * WhatsApp preference in My Account.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Lets logged-in customers switch WhatsApp messages on or off under My Account → Account details.
 */
final class Account {

	private const FIELD = 'ordervox_optin';

	/**
	 * Consent.
	 *
	 * @var Consent
	 */
	private Consent $consent;

	/**
	 * Constructor.
	 *
	 * @param Consent $consent Consent.
	 */
	public function __construct( Consent $consent ) {
		$this->consent = $consent;
	}

	/**
	 * Registers hooks when the opt-in is switched on.
	 */
	public function register(): void {
		if ( ! $this->consent->is_enabled() ) {
			return;
		}
		add_action( 'woocommerce_edit_account_form', array( $this, 'render' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save' ) );
	}

	/**
	 * Prints the checkbox.
	 */
	public function render(): void {
		$checked = $this->consent->user_opted_in( get_current_user_id() );
		?>
		<fieldset class="ordervox-account">
			<legend><?php esc_html_e( 'WhatsApp notifications', 'ordervox' ); ?></legend>
			<p class="woocommerce-form-row form-row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="1" <?php checked( $checked ); ?> />
					<span><?php echo esc_html( $this->consent->label() ); ?></span>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Saves the choice. WooCommerce has already verified the account form nonce.
	 *
	 * @param int $user_id User id.
	 */
	public function save( $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WC_Form_Handler::save_account_details().
		$this->consent->set_user_choice( (int) $user_id, ! empty( $_POST[ self::FIELD ] ) );
	}
}
