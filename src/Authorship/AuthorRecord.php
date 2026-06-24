<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorRecord {
    private int $id;
    private string $name;
    private string $slug;
    private string $url;
    private string $email;
    private string $avatar;
    private array $avatars;
    private array $fields;

    public function __construct(
        int $id,
        string $name,
        string $slug,
        string $url,
        string $email,
        string $avatar,
        array $avatars,
        array $fields
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->url = $url;
        $this->email = $email;
        $this->avatar = $avatar;
        $this->avatars = $avatars;
        $this->fields = $fields;
    }

    public function id(): int {
        return $this->id;
    }

    public function field( string $key, string $default = "" ): string {
        return isset( $this->fields[ $key ] ) && is_scalar( $this->fields[ $key ] )
            ? trim( (string) $this->fields[ $key ] )
            : $default;
    }

    public function to_array(): array {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "slug" => $this->slug,
            "url" => $this->url,
            "email" => $this->email,
            "avatar" => $this->avatar,
            "avatars" => $this->avatars,
            "fields" => $this->fields,
        ];
    }
}
