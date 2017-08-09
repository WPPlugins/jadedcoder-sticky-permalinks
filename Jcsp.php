<?php
define( 'JCSP_DB_VERSION', '0.1beta' );

class Jcsp
{
    public function getPostArchiveTableName()
    {
       global $wpdb;
       return $wpdb->prefix . 'jcsp_post_permalinks';
    }

    public function getCategoryArchiveTableName()
    {
       global $wpdb;
       return $wpdb->prefix . 'jcsp_category_permalinks';
    }

    public function getPostPermalink( $postId )
    {
        $permalink = get_permalink( $postId );

        //cut out the http://domain.com/
        $permalink = explode( '/', $permalink );
        array_shift( $permalink );
        array_shift( $permalink );
        array_shift( $permalink );
        $permalink = implode( '/', $permalink );

        if( substr( $permalink, -1 ) == '/' )
        {
            $permalink = substr( $permalink, 0, -1 );
        }
        
        return $permalink;
    }
    
    public function getCategoryPermalink( $categoryId )
    {
        global $wpdb;
        $permalink = get_category_link( $categoryId );

        //TODO: This is a little hack that gives a permalink as it will be AFTER the new category is saved, due to the fact that wordpress for some reason calls the edit_category action BEFORE the data is saved... If wp ever decides to change field names then this will break... But I doubt that
        if( isset( $_POST[ 'category_nicename' ] ) )
        {
            $category = $wpdb->get_results( 'SELECT category_nicename FROM ' . $wpdb->categories . ' WHERE cat_ID = ' . $categoryId );
            $permalink = str_replace( $category->category_nicename, $_POST[ 'category_nicename' ], $permalink );
        }

        //cut out the http://domain.com/
        $permalink = explode( '/', $permalink );
        array_shift( $permalink );
        array_shift( $permalink );
        array_shift( $permalink );
        $permalink = implode( '/', $permalink );
        
        if( substr( $permalink, -1 ) == '/' )
        {
            $permalink = substr( $permalink, 0, -1 );
        }

        return $permalink;
    }
    
    public function postUpdated( $postId )
    {
        global $wpdb;
        $permalink = $this->getPostPermalink( $postId );
        $exists = $wpdb->get_results( 'SELECT post_id FROM ' . $this->getPostArchiveTableName() . ' WHERE post_permalink = "' . $wpdb->escape( $permalink ) . '"' );
        if( count( $exists ) > 0 )
        {
            $wpdb->query( 'UPDATE ' . $this->getPostArchiveTableName() . ' SET post_id = ' . $postId . ' WHERE post_permalink = "' . $wpdb->escape( $permalink ) . '"' );
        }
        else
        {
            $wpdb->query( 'INSERT INTO ' . $this->getPostArchiveTableName() . ' ( post_id, post_permalink ) VALUES ( ' . $postId . ', "' . $wpdb->escape( $permalink ) . '")' );
        }
    }
    
    public function categoryUpdated( $categoryId )
    {
        global $wpdb;
        $posts = $wpdb->get_results( 'SELECT post_id FROM ' . $wpdb->post2cat . ' WHERE category_id = ' . $categoryId );
        foreach( $posts as $post )
        {
            $this->postUpdated( $post->post_id );
        }
        $permalink = $this->getCategoryPermalink( $categoryId );
        $exists = $wpdb->get_results( 'SELECT category_id FROM ' . $this->getCategoryArchiveTableName() . ' WHERE category_permalink = "' . $wpdb->escape( $permalink ) . '"' );
        if( count( $exists ) > 0 )
        {
            $wpdb->query( 'UPDATE ' . $this->getCategoryArchiveTableName() . ' SET category_id = ' . $categoryId . ' WHERE category_permalink = "' . $wpdb->escape( $permalink ) . '"' );
        }
        else
        {
            $wpdb->query( 'INSERT INTO ' . $this->getCategoryArchiveTableName() . ' ( category_id, category_permalink ) VALUES ( ' . $categoryId . ', "' . $wpdb->escape( $permalink ) . '")' );
        }
    }

    function ensureTables()
    {
        $this->ensurePostArchiveTable();
        $this->ensureCategoryArchiveTable();
    }
    
    function ensurePostArchiveTable()
    {
        global $wpdb;
        $table_name = $this->getPostArchiveTableName();
        if( $wpdb->get_var( 'show tables like "' . $table_name . '"' ) != $table_name )
        {
            $sql = 'CREATE TABLE ' . $table_name . ' (
                    post_archive_id mediumint(9) NOT NULL AUTO_INCREMENT,
                    post_id mediumint(9) NOT NULL,
                    post_permalink VARCHAR(55) NOT NULL,
                    UNIQUE KEY post_archive_id (post_archive_id)
                    );';
            require_once ABSPATH . 'wp-admin/upgrade-functions.php';
            dbDelta($sql);
            $this->initializePostArchiveTable();
            add_option( 'JCSP_DB_VERSION', JCSP_DB_VERSION );
        }
    }
    
    function ensureCategoryArchiveTable()
    {
        global $wpdb;
        $table_name = $this->getCategoryArchiveTableName();
        if( $wpdb->get_var( 'show tables like "' . $table_name . '"' ) != $table_name )
        {
            $sql = 'CREATE TABLE ' . $table_name . ' (
                    category_archive_id mediumint(9) NOT NULL AUTO_INCREMENT,
                    category_id mediumint(9) NOT NULL,
                    category_permalink VARCHAR(55) NOT NULL,
                    UNIQUE KEY category_archive_id (category_archive_id)
                    );';

            require_once ABSPATH . 'wp-admin/upgrade-functions.php';
            dbDelta( $sql );
            $this->initializeCategoryArchiveTable();
            add_option( 'JCSP_DB_VERSION', JCSP_DB_VERSION );
        }
    }
    
    function initializePostArchiveTable()
    {
        global $wpdb;
        $posts = $wpdb->get_results( 'SELECT ID FROM ' . $wpdb->posts );
        foreach( $posts as $post )
        {
            $permalink = $this->getPostPermalink( $post->ID );
            $sql = 'INSERT INTO ' . $this->getPostArchiveTableName() . '
                              ( post_id, post_permalink )
                       VALUES ( ' . $post->ID . ', "' . $wpdb->escape( $permalink ) . '" )';
            $wpdb->query( $sql );
        }
    }

    function initializeCategoryArchiveTable()
    {
        global $wpdb;
        $categories = $wpdb->get_results( 'SELECT cat_ID FROM ' . $wpdb->categories );
        foreach( $categories as $category )
        {
            $permalink = $this->getCategoryPermalink( $category->cat_ID );
            $sql = 'INSERT INTO ' . $this->getCategoryArchiveTableName() . '
                              ( category_id, category_permalink )
                       VALUES ( ' . $category->cat_ID . ', "' . $wpdb->escape( $permalink ) . '" )';
            $wpdb->query( $sql );
        }
    }
    
    function tryRedirect()
    {
        global $wpdb;

        $inLink = $_SERVER[ 'REQUEST_URI' ];
        if( substr( $inLink, 0, 1 ) == '/' )
        {
            $inLink = substr( $inLink, 1 );
        }
        if( substr( $inLink, -1 ) == '/' )
        {
            $inLink = substr( $inLink, 0, -1 );
        }
        
        //user is accessing the index page, we have to return or this will cause problems
        if( $inLink == '' )
        {
            return;
        }

        //try a post redirect
        $destination = $wpdb->get_results( 'SELECT post_id FROM ' . $this->getPostArchiveTableName() . ' WHERE post_permalink = "' . $wpdb->escape( $inLink ) . '"' );
        if( count( $destination ) > 0 )
        {
            $permalink = $this->getPostPermalink( $destination[ 0 ]->post_id );
            if( $permalink != $inLink )
            {
                wp_redirect( '/' . $permalink );
                exit;
            }
        }

        //try a category redirect
        $destination = $wpdb->get_results( 'SELECT category_id FROM ' . $this->getCategoryArchiveTableName() . ' WHERE category_permalink = "' . $wpdb->escape( $inLink ) . '"' );
        if( count( $destination ) > 0 )
        {
            $permalink = $this->getCategoryPermalink( $destination[ 0 ]->category_id );
            if( $permalink != $inLink )
            {
                wp_redirect( '/' . $permalink );
                exit;
            }
        }
    }
}
?>