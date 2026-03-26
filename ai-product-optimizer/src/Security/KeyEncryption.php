<?php
/**
 * API key encryption / decryption utility.
 *
 * Uses AES-256-CBC with WordPress's AUTH_KEY + SECURE_AUTH_KEY as the
 * passphrase and SECURE_AUTH_SALT as the IV source. Keys are never stored
 * in plaintext in the database.
 *
 * @package AIProductOptimizer\Security
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Security;

use AIProductOptimizer\Exceptions\EncryptionException;

/**
 * Class KeyEncryption
 */
class KeyEncryption {

	private const CIPHER = 'AES-256-CBC';

	/**
	 * Encrypt a plaintext API key.
	 *
	 * @param string $plaintext Raw API key.
	 * @return string Base64-encoded ciphertext (safe for wp_options storage).
	 * @throws EncryptionException If encryption fails.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( empty( $plaintext ) ) {
			return '';
		}

		[ $key, $iv ] = self::derive_key_iv();

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			throw new EncryptionException( 'Failed to encrypt API key.' );
		}

		// Store IV prepended so we can retrieve it for decryption.
		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a previously encrypted API key.
	 *
	 * @param string $encrypted Base64-encoded ciphertext from encrypt().
	 * @return string Plaintext API key.
	 * @throws EncryptionException If decryption fails.
	 */
	public static function decrypt( string $encrypted ): string {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$raw = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw ) {
			throw new EncryptionException( 'Invalid base64 encoding in stored API key.' );
		}

		$iv_length  = openssl_cipher_iv_length( self::CIPHER );
		$iv         = substr( $raw, 0, (int) $iv_length );
		$ciphertext = substr( $raw, (int) $iv_length );

		[ $key ] = self::derive_key_iv();

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $plaintext ) {
			throw new EncryptionException( 'Failed to decrypt API key. The WordPress secret keys may have changed.' );
		}

		return $plaintext;
	}

	/**
	 * Derive the 32-byte key and 16-byte IV from WordPress secret constants.
	 *
	 * @return array{0: string, 1: string}  [ key, iv ]
	 */
	private static function derive_key_iv(): array {
		$auth_key        = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'aipo-fallback-key';
		$secure_auth_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'aipo-fallback-secure';
		$secure_auth_salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'aipo-fallback-salt';

		$key = substr( hash( 'sha256', $auth_key . $secure_auth_key, true ), 0, 32 );
		$iv  = substr( hash( 'sha256', $secure_auth_salt, true ), 0, (int) openssl_cipher_iv_length( self::CIPHER ) );

		return array( $key, $iv );
	}
}
