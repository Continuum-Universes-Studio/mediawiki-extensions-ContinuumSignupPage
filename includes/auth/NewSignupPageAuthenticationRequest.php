<?php

use MediaWiki\Auth\AuthenticationRequest;
use ContinuumUniversesHooks;
/**
 * @ingroup Auth
 * @since MediaWiki 1.27
 * @phan-file-suppress PhanTypeMismatchReturn It appears that phan seems to hate the retval of getFieldInfo()...
 */
class NewSignupPageAuthenticationRequest extends AuthenticationRequest {
	public $required = self::REQUIRED; // only ToS check is mandatory

	/**
	 * @var int Email invitation source identifier to be stored in the
	 * user_email_track table
	 * @see /extensions/MiniInvite/includes/UserEmailTrack.class.php for details
	 */
	public $from;

	/**
	 * @var string|int Username of the person who referred the user creating an
	 * account to the wiki; used to give out points to the referring user and
	 * also automatically friend them and the new user if that configuration
	 * setting is enabled
	 */
	public $referral;

	/**
	 * @var bool Was the "I agree to the terms of service"
	 * checkbox checked? It must be in order for the account creation process
	 * to continue.
	 */
	public $wpTermsOfService;

	/**
	 * @var string|null Birth date used to determine whether the user qualifies
	 * for the adultreader group.
	 */
	public $wpBirthDate;

	/**
	 * @var bool Did the user confirm that they were honest about their age?
	 */
	public $wpAgeHonesty;

	/**
	 * @param WebRequest $request
	 */
	public function __construct( $request ) {
		$this->from = $request->getInt( 'from' );
		$this->referral = $request->getVal( 'referral' );
	}

	private static function getMinimumAge(): int {
		if (
			class_exists( Hooks::class )
			&& method_exists( Hooks::class, 'getMinimumAge' )
		) {
			return Hooks::getMinimumAge();
		}

		return 18;
	}

	public static function normalizeBirthDateValue( ?string $value ): ?string {
		$value = trim( (string)$value );
		if ( $value === '' ) {
			return null;
		}

		if (
			class_exists( Hooks::class )
			&& method_exists( Hooks::class, 'normalizeBirthDateValue' )
		) {
			return Hooks::normalizeBirthDateValue( $value );
		}

		// Accept ISO-style and US-style dates and normalize to YYYY-MM-DD.
		if ( preg_match( '/^\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}$/', $value ) ) {
			return preg_replace( '/[\/\.]/', '-', $value );
		}

		if ( preg_match( '/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value ) ) {
			list( $month, $day, $year ) = explode( '/', $value );
			$month = (int)$month;
			$day = (int)$day;
			$year = (int)$year;
			if ( checkdate( $month, $day, $year ) ) {
				return sprintf( '%04d-%02d-%02d', $year, $month, $day );
			}
		}

		return null;
	}

	public static function isBirthDateAtLeastMinimumAge( ?string $value ): bool {
		$normalized = self::normalizeBirthDateValue( $value );
		if ( $normalized === null ) {
			return false;
		}

		if (
			class_exists( Hooks::class )
			&& method_exists( Hooks::class, 'isBirthDateAtLeastMinimumAge' )
		) {
			return Hooks::isBirthDateAtLeastMinimumAge( $normalized );
		}

		$birth = new DateTimeImmutable( $normalized );
		$threshold = $birth->modify( '+' . self::getMinimumAge() . ' years' );
		if ( $threshold === false ) {
			return false;
		}

		return $threshold <= new DateTimeImmutable( 'today' );
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		global $wgNewSignupPageToSURL, $wgNewSignupPagePPURL;
		return [
			'from' => [
				'type' => 'hidden',
				'optional' => true,
				'value' => $this->from
			],
			'referral' => [
				'type' => 'hidden',
				'optional' => true,
				'value' => $this->referral
			],
			'wpBirthDate' => [
				'type' => 'string',
				'label' => wfMessage(
					'newsignuppage-loginform-birthdate',
					self::getMinimumAge()
				),
				'sensitive' => true
			],
			'wpAgeHonesty' => [
				'type' => 'checkbox',
				'label' => wfMessage( 'newsignuppage-loginform-age-honesty' )
			],
			'wpTermsOfService' => [
				'type' => 'checkbox',
				'label' => wfMessage(
					'newsignuppage-loginform-tos',
					$wgNewSignupPageToSURL,
					$wgNewSignupPagePPURL,
					self::getMinimumAge()
				)
			]
		];
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		// We always want to use this request, so ignore parent's return value.
		parent::loadFromSubmission( $data );

		return true;
	}
}
