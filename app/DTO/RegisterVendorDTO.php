<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

/**
 * Class RegisterVendorDTO
 *
 * Description of administrative platform component RegisterVendorDTO.
 *
 * @package vendor-marketplace
 */
final class RegisterVendorDTO extends BaseDTO
{
    public function __construct(
        public readonly string $storeName = '',
        public readonly string $userEmail = '',
        public readonly string $storeSlug = '',
        public readonly string $phone = '',
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly string $userPass = ''
    ) {}

    /**
     * FromArray functionality helper.
     *
     * @param array $data Description index.
     * @return static Output payload.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            storeName: (string) ($data['store_name'] ?? ''),
            userEmail: (string) ($data['user_email'] ?? ''),
            storeSlug: (string) ($data['store_slug'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            userPass: (string) ($data['user_pass'] ?? '')
        );
    }

    /**
     * ToArray functionality helper.
     *
     * @return array Output payload.
     */
    public function toArray(): array
    {
        return [
            'store_name' => $this->storeName,
            'user_email' => $this->userEmail,
            'store_slug' => $this->storeSlug,
            'phone'      => $this->phone,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'user_pass'  => $this->userPass,
        ];
    }
}
