<?php
/**
 * Unit tests for KeyEncryption.
 *
 * @package AIProductOptimizer\Tests\Unit
 */

declare( strict_types=1 );

namespace AIProductOptimizer\Tests\Unit;

use AIProductOptimizer\Exceptions\EncryptionException;
use AIProductOptimizer\Security\KeyEncryption;
use PHPUnit\Framework\TestCase;

/**
 * Class KeyEncryptionTest
 */
class KeyEncryptionTest extends TestCase {

	public function test_encrypt_returns_non_empty_string(): void {
		$encrypted = KeyEncryption::encrypt( 'sk-test-api-key-12345' );
		$this->assertNotEmpty( $encrypted );
		$this->assertNotSame( 'sk-test-api-key-12345', $encrypted );
	}

	public function test_encrypt_empty_string_returns_empty(): void {
		$this->assertSame( '', KeyEncryption::encrypt( '' ) );
	}

	public function test_decrypt_empty_string_returns_empty(): void {
		$this->assertSame( '', KeyEncryption::decrypt( '' ) );
	}

	public function test_encrypt_decrypt_round_trip(): void {
		$original  = 'sk-test-secret-api-key-abcdefgh';
		$encrypted = KeyEncryption::encrypt( $original );
		$decrypted = KeyEncryption::decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	public function test_multiple_keys_encrypt_differently(): void {
		$key1 = KeyEncryption::encrypt( 'key-one' );
		$key2 = KeyEncryption::encrypt( 'key-two' );

		$this->assertNotSame( $key1, $key2 );
	}

	public function test_decrypt_invalid_base64_throws(): void {
		$this->expectException( EncryptionException::class );
		KeyEncryption::decrypt( '!!!not-valid-base64!!!@@@' );
	}

	public function test_same_key_produces_consistent_output(): void {
		// Encryption is deterministic given same IV derivation.
		$key  = 'consistent-key';
		$enc1 = KeyEncryption::encrypt( $key );
		$enc2 = KeyEncryption::encrypt( $key );

		// The same plaintext with the same derived IV should produce the same ciphertext.
		$this->assertSame( $enc1, $enc2 );

		// Both should decrypt to the same original.
		$this->assertSame( $key, KeyEncryption::decrypt( $enc1 ) );
		$this->assertSame( $key, KeyEncryption::decrypt( $enc2 ) );
	}
}
