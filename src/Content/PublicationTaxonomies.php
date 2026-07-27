<?php

namespace smp_publication_integration\Content;

use Hexa\PluginCore\Taxonomies\TaxonomyRegistry;
use smp_publication_integration\Authorship\AuthorAssignmentRepository;

defined( 'ABSPATH' ) || exit;

final class PublicationTaxonomies {
    private static ?TaxonomyRegistry $registry = null;

    public static function registry(): TaxonomyRegistry {
        if ( self::$registry instanceof TaxonomyRegistry ) {
            return self::$registry;
        }

        self::$registry = new TaxonomyRegistry( [ 'hook_priority' => 8 ] );
        self::$registry
            ->add(
                [
                    'id'           => 'article-types',
                    'taxonomy'     => ArticleTypes::TAXONOMY,
                    'label'        => 'Article Types',
                    'description'  => 'The canonical article classification used to select each article schema object.',
                    'owner'        => 'SMP Publication Integration',
                    'enabled'      => [ ArticleTypes::class, 'is_enabled' ],
                    'object_types' => [ ArticleTypes::class, 'supported_post_types' ],
                    'args'         => [ ArticleTypes::class, 'taxonomy_args' ],
                ]
            )
            ->add(
                [
                    'id'           => 'publication-authors',
                    'taxonomy'     => AuthorAssignmentRepository::TAXONOMY,
                    'label'        => 'Publication Authors',
                    'description'  => 'Internal ordered author assignments synchronized with native WordPress authors.',
                    'owner'        => 'SMP Publication Integration',
                    'enabled'      => true,
                    'object_types' => [ AuthorAssignmentRepository::class, 'supported_post_types' ],
                    'args'         => [ AuthorAssignmentRepository::class, 'taxonomy_args' ],
                ]
            );

        return self::$registry;
    }
}
