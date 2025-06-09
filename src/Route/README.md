# Pollora Router

Pollora's Laravel/WordPress hybrid routing system combines the power of the Laravel router with native WordPress integration.

## Vue d'ensemble

Pollora's extended router offers:
- Full support for standard Laravel routes
- Integration with WordPress conditional tags via `Route::wp()`
- Automatic binding system for WordPress data
- Fallback WordPress template resolution

## Using WordPress Routes

### Basic syntax

```php
use Illuminate\Support\Facades\Route;

// Route for the home page
Route::wp('front', function () {
    return view('home');
});

// Route for individual posts
Route::wp('single', function () {
    return view('single');
});

// Route with controller
Route::wp('category', CategoryController::class);
```

### Parameter syntax

Many WordPress conditions accept parameters. You can pass them between the condition and the action:

```php
// Route for a specific post type
Route::wp('is_singular', 'realisations', function () {
    return view('single-realisations');
});

// Route for multiple post types
Route::wp('is_singular', ['post', 'page'], function () {
    return view('single-content');
});

// With a controller
Route::wp('is_singular', 'realisations', RealisationsController::class);

// Specific page by slug
Route::wp('is_page', 'contact', function () {
    return view('contact');
});

// Specific category
Route::wp('is_category', 'news', function () {
    return view('news-category');
});
```

### Supported WordPress conditions

- `front` - Home page (is_front_page)
- `home` - Blog page (is_home)
- `single` - Single post (is_single)
- `page` - Page (is_page)
- `category` - Category archive (is_category)
- `tag` - Tag archive (is_tag)
- `author` - Archive d'auteur (is_author)
- `archive` - Archives générales (is_archive)
- `search` - Page de recherche (is_search)
- `404` - Page 404 (is_404)

## Système d'Injection de Dépendance WordPress

Le routeur utilise le système d'injection de dépendance natif de Laravel pour injecter automatiquement les objets WordPress dans vos routes et contrôleurs.

### Types WordPress supportés

Le système peut injecter automatiquement ces types WordPress :

- **`WP_Post`** - L'article ou page actuel
- **`WP_Term`** - Le terme de taxonomie (catégorie, tag, etc.)
- **`WP_User`** - L'utilisateur/auteur actuel
- **`WP_Query`** - L'objet de requête principal
- **`WP`** - L'objet WordPress principal

### Comment ça fonctionne

Le système utilise deux composants principaux :

1. **Service Providers Laravel** : Enregistrent les types WordPress dans le container d'injection de dépendance
2. **Middleware WordPress** : Évalue les conditions WordPress pour déterminer si une route doit être exécutée

Les routes WordPress sont créées comme des routes catch-all avec un middleware qui vérifie les conditions WordPress. Si la condition ne correspond pas, une erreur 404 est retournée.

Cela permet à Laravel de résoudre automatiquement les types WordPress dans :

- Les closures de routes
- Les méthodes de contrôleurs
- Les constructeurs (avec prudence)
- Toute méthode résolue par le container

### Injection automatique par type

Il suffit de déclarer le type dans votre fonction pour que l'objet soit automatiquement injecté :

#### Articles et Pages

```php
Route::wp('single', function (WP_Post $post) {
    return view('single', [
        'title' => $post->post_title,
        'content' => $post->post_content,
        'type' => $post->post_type
    ]);
});

// Avec plusieurs paramètres
Route::wp('single', function (WP_Post $post, WP_Query $query) {
    return view('single', [
        'post' => $post,
        'related_posts' => $query->posts,
        'pagination' => $query->max_num_pages
    ]);
});
```

#### Archives de Taxonomie

```php
Route::wp('category', function (WP_Term $term) {
    return view('category', [
        'category' => $term,
        'name' => $term->name,
        'slug' => $term->slug,
        'count' => $term->count
    ]);
});

Route::wp('tag', function (WP_Term $term, WP_Query $query) {
    return view('tag', [
        'tag' => $term,
        'posts' => $query->posts,
        'posts_count' => $query->found_posts
    ]);
});
```

#### Archives d'Auteur

```php
Route::wp('author', function (WP_User $author, WP_Query $query) {
    return view('author', [
        'author' => $author,
        'posts' => $query->posts,
        'posts_count' => $query->found_posts,
        'bio' => get_user_meta($author->ID, 'description', true)
    ]);
});
```

### Noms de paramètres personnalisés

Le nom du paramètre n'a pas d'importance, seul le type compte :

```php
Route::wp('single', function (WP_Post $article, WP_Query $requete) {
    // $article contiendra l'objet WP_Post
    // $requete contiendra l'objet WP_Query
    
    return view('single', compact('article', 'requete'));
});
```

## Exemples Pratiques

### 1. Page d'accueil avec données spécifiques

```php
Route::wp('front', function (WP_Query $query) {
    $featured_posts = get_posts([
        'numberposts' => 3,
        'meta_key' => 'featured',
        'meta_value' => 'yes'
    ]);
    
    return view('home', [
        'featured_posts' => $featured_posts,
        'latest_posts' => $query->posts,
        'is_main_query' => $query->is_main_query()
    ]);
});
```

### 2. Article avec données de contexte

```php
Route::wp('single', function (WP_Post $post) {
    $related_posts = get_posts([
        'numberposts' => 3,
        'exclude' => [$post->ID],
        'category__in' => wp_get_post_categories($post->ID)
    ]);
    
    return view('single', [
        'post' => $post,
        'related_posts' => $related_posts,
        'categories' => get_the_category($post->ID),
        'tags' => get_the_tags($post->ID)
    ]);
});
```

### 3. Archive de catégorie avec pagination

```php
Route::wp('category', function (WP_Term $category, WP_Query $query) {
    return view('category', [
        'category' => $category,
        'posts' => $query->posts,
        'pagination' => [
            'current_page' => $query->query_vars['paged'] ?? 1,
            'max_pages' => $query->max_num_pages,
            'found_posts' => $query->found_posts
        ],
        'category_description' => $category->description
    ]);
});
```

### 4. Page d'auteur avec statistiques

```php
Route::wp('author', function (WP_User $author, WP_Query $query) {
    return view('author', [
        'author' => $author,
        'posts' => $query->posts,
        'posts_count' => $query->found_posts,
        'author_bio' => get_user_meta($author->ID, 'description', true),
        'author_website' => $author->user_url,
        'social_links' => [
            'twitter' => get_user_meta($author->ID, 'twitter', true),
            'linkedin' => get_user_meta($author->ID, 'linkedin', true)
        ]
    ]);
});
```

### 5. Archive de tag avec nuage de tags

```php
Route::wp('tag', function (WP_Term $tag, WP_Query $query) {
    $related_tags = get_terms([
        'taxonomy' => 'post_tag',
        'exclude' => [$tag->term_id],
        'number' => 10
    ]);
    
    return view('tag', [
        'tag' => $tag,
        'posts' => $query->posts,
        'posts_count' => $query->found_posts,
        'related_tags' => $related_tags
    ]);
});
```

### 6. Gestion avec paramètres optionnels

```php
Route::wp('archive', function (WP_Query $query, WP_Term $term = null, WP_User $author = null) {
    $data = [
        'posts' => $query->posts,
        'pagination' => $query->max_num_pages
    ];
    
    if ($term) {
        $data['archive_type'] = 'taxonomy';
        $data['archive_title'] = $term->name;
        $data['term'] = $term;
    } elseif ($author) {
        $data['archive_type'] = 'author';
        $data['archive_title'] = $author->display_name;
        $data['author'] = $author;
    } else {
        $data['archive_type'] = 'date';
        $data['archive_title'] = get_the_date('F Y');
    }
    
    return view('archive', $data);
});
```

## Fallback et Template Hierarchy

Si aucune route WordPress ne correspond, le système utilise la hiérarchie de templates WordPress standard :

```php
// Cette route sera utilisée en dernier recours
Route::wp('404', function () {
    return response()->view('404', [], 404);
});
```

## Bonnes Pratiques

### 1. Utiliser l'injection de dépendance plutôt que les globals

```php
// ✅ Recommandé - utilise l'injection de dépendance
Route::wp('single', function (WP_Post $post) {
    return view('single', compact('post'));
});

// ❌ Éviter - accès direct aux globals
Route::wp('single', function () {
    global $post;
    return view('single', ['post' => $post]);
});
```

### 2. Utiliser des paramètres optionnels pour les cas incertains

```php
Route::wp('archive', function (WP_Query $query, WP_Term $term = null) {
    if (!$term) {
        // Gestion du cas où ce n'est pas une archive de taxonomie
        return view('date-archive', ['query' => $query]);
    }
    
    return view('term-archive', ['term' => $term, 'query' => $query]);
});
```

### 3. Combiner avec les middlewares Laravel

```php
Route::wp('author', AuthorController::class)
    ->middleware(['cache:3600']);
```

### 4. Utiliser des contrôleurs pour la logique complexe

```php
class CategoryController
{
    public function __invoke(WP_Term $category, WP_Query $query)
    {
        $posts = collect($query->posts);
        $featured = $posts->filter(fn($post) => get_post_meta($post->ID, 'featured', true));
        $regular = $posts->reject(fn($post) => get_post_meta($post->ID, 'featured', true));
        
        return view('category', [
            'category' => $category,
            'featured_posts' => $featured,
            'regular_posts' => $regular,
            'pagination' => $query->max_num_pages
        ]);
    }
}
```

### 5. Injection dans les contrôleurs

L'injection fonctionne parfaitement avec les contrôleurs Laravel :

```php
class ArchiveController extends Controller
{
    public function __construct(
        private readonly PostService $postService
    ) {}

    public function __invoke(WP_Term $term, WP_Query $query): View
    {
        $currentPage = get_query_var('paged') ?: 1;
        
        // Plus besoin de get_queried_object() et vérifications manuelles
        // Le terme est automatiquement injecté et typé
        
        $data = $this->postService->getPosts($currentPage, $term);
        $pagination = $this->postService->getPagination($currentPage, $data['maxPages'], $term);

        return view('pages.blog.cat', [
            'posts' => $data['posts'],
            'categories' => $data['categories'],
            'pagination' => $pagination,
            'activeCategoryId' => $term->term_id,
        ]);
    }
}
```

```php
class SingleController extends Controller
{
    public function __invoke(WP_Post $post): View
    {
        return view('single', [
            'post' => $post,
            'related_posts' => $this->getRelatedPosts($post),
            'categories' => get_the_category($post->ID)
        ]);
    }
}
```

### 6. Gestion des paramètres optionnels

Pour gérer les cas où certains objets peuvent ne pas être disponibles :

```php
class ArchiveController extends Controller
{
    public function __invoke(WP_Query $query, WP_Term $term = null, WP_User $author = null): View
    {
        if ($term) {
            return $this->handleTermArchive($term, $query);
        }
        
        if ($author) {
            return $this->handleAuthorArchive($author, $query);
        }
        
        return $this->handleDateArchive($query);
    }
}

## Configuration

Les conditions WordPress peuvent être personnalisées dans `config/wordpress.php` :

```php
'routing' => [
    'conditions' => [
        'front' => 'is_front_page',
        'blog' => 'is_home',
        'custom' => 'my_custom_condition',
        // ...
    ],
],
```