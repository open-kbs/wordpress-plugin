<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class OpenKBS_Filesystem_API
 *
 * Handles filesystem operations through REST API endpoints
 *
 * @since 1.0.0
 */
class OpenKBS_Filesystem_API {
    private $plugins_dir;
    private $is_enabled;

    public function __construct() {
        $this->plugins_dir = WP_PLUGIN_DIR;
        $this->is_enabled = get_option('openkbs_filesystem_api_enabled', false);    

        if ($this->is_enabled) {
            add_action('rest_api_init', array($this, 'register_filesystem_endpoints'));
        }
    }

    public function check_permissions() {
        return $this->is_enabled && current_user_can('manage_options');
    }

    public function register_filesystem_endpoints() {
        // List directory contents (both root and subpaths)
        register_rest_route('openkbs/v1', '/filesystem/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_directory'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'path' => array(
                    'required' => false,
                    'default' => '',
                ),
            ),
        ));

        // Create directory
        register_rest_route('openkbs/v1', '/filesystem/mkdir', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_directory'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        // Delete file or directory
        register_rest_route('openkbs/v1', '/filesystem/delete', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_item'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'path' => array(
                    'required' => true,
                ),
            ),
        ));

        // Read file
        register_rest_route('openkbs/v1', '/filesystem/read', array(
            'methods' => 'GET',
            'callback' => array($this, 'read_file'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'path' => array(
                    'required' => true,
                ),
            ),
        ));

        // Create/Update file
        register_rest_route('openkbs/v1', '/filesystem/write', array(
            'methods' => 'POST',
            'callback' => array($this, 'write_file'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        // Copy file or directory
        register_rest_route('openkbs/v1', '/filesystem/copy', array(
            'methods' => 'POST',
            'callback' => array($this, 'copy_item'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        // List directory contents recursively
        register_rest_route('openkbs/v1', '/filesystem/list-recursive', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_directory_recursive'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'path' => array(
                    'required' => false,
                    'default' => '',
                ),
            ),
        ));

        // Read directory contents recursively (including file contents)
        register_rest_route('openkbs/v1', '/filesystem/read-recursive', array(
            'methods' => 'GET',
            'callback' => array($this, 'read_directory_recursive'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'path' => array(
                    'required' => false,
                    'default' => '',
                ),
            ),
        ));
    }

    private function validate_path($path) {
        // Block any paths containing parent directory references
        if (strpos($path, '..') !== false) {
            return false;
        }
    
        $full_path = $this->plugins_dir . '/' . ltrim($path, '/');
        $real_path = realpath($full_path);
        
        if ($real_path === false) {
            return $full_path; // For new files/directories
        }
    
        // Ensure the path is within plugins directory
        if (strpos($real_path, $this->plugins_dir) !== 0) {
            return false;
        }
    
        return $full_path;
    }

    public function list_directory($request) {
        $path = $request->get_param('path');
    
        // Handle root directory case
        if (empty($path)) {
            $full_path = $this->plugins_dir;
        } else {
            $full_path = $this->validate_path($path);
        }    
    
        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }
    
        if (!is_dir($full_path)) {
            return new WP_Error('not_directory', 'Specified path is not a directory', array('status' => 400));
        }
    
        $items = array();
        $dir = new DirectoryIterator($full_path);
        
        foreach ($dir as $item) {
            if ($item->isDot()) continue;
            
            $items[] = array(
                'name' => $item->getFilename(),
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->getSize(),
                'modified' => date('Y-m-d H:i:s', $item->getMTime())
            );
        }
    
        return new WP_REST_Response($items, 200);
    }

    public function create_directory($request) {
        $path = $request->get_param('path');
        $full_path = $this->validate_path($path);

        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        if (file_exists($full_path)) {
            return new WP_Error('exists', 'Directory already exists', array('status' => 400));
        }

        if (!mkdir($full_path, 0755, true)) {
            return new WP_Error('creation_failed', 'Failed to create directory', array('status' => 500));
        }

        return new WP_REST_Response(array('message' => 'Directory created successfully'), 201);
    }

    public function delete_item($request) {
        $path = $request->get_param('path');
        $full_path = $this->validate_path($path);

        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        if (!file_exists($full_path)) {
            return new WP_Error('not_found', 'File or directory not found', array('status' => 404));
        }

        if (is_dir($full_path)) {
            if (!$this->delete_directory_recursive($full_path)) {
                return new WP_Error('deletion_failed', 'Failed to delete directory', array('status' => 500));
            }
        } else {
            if (!unlink($full_path)) {
                return new WP_Error('deletion_failed', 'Failed to delete file', array('status' => 500));
            }
        }

        return new WP_REST_Response(array('message' => 'Item deleted successfully'), 200);
    }

    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }

    public function read_file($request) {
        $path = $request->get_param('path');
        $full_path = $this->validate_path($path);
    
        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }
    
        if (!file_exists($full_path) || !is_file($full_path)) {
            return new WP_Error('not_found', 'File not found', array('status' => 404));
        }
    
        $content = file_get_contents($full_path);
        if ($content === false) {
            return new WP_Error('read_failed', 'Failed to read file', array('status' => 500));
        }
    
        return new WP_REST_Response(array(
            'content' => $content,
            'size' => filesize($full_path),
            'modified' => date('Y-m-d H:i:s', filemtime($full_path))
        ), 200);
    }

    public function write_file($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');
        $full_path = $this->validate_path($path);

        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        // Extract the directory path from the full path
        $directory = dirname($full_path);

        // Check if the directory exists, if not, create it
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return new WP_Error('mkdir_failed', 'Failed to create directories', array('status' => 500));
            }
        }

        // Attempt to write the file
        if (file_put_contents($full_path, $content) === false) {
            return new WP_Error('write_failed', 'Failed to write file', array('status' => 500));
        }

        return new WP_REST_Response(array('message' => 'File written successfully'), 200);
    }

    public function copy_item($request) {
        $source = $request->get_param('source');
        $destination = $request->get_param('destination');
        
        $source_path = $this->validate_path($source);
        $destination_path = $this->validate_path($destination);

        if ($source_path === false || $destination_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        if (!file_exists($source_path)) {
            return new WP_Error('not_found', 'Source file or directory not found', array('status' => 404));
        }

        if (is_dir($source_path)) {
            if (!$this->copy_directory_recursive($source_path, $destination_path)) {
                return new WP_Error('copy_failed', 'Failed to copy directory', array('status' => 500));
            }
        } else {
            if (!copy($source_path, $destination_path)) {
                return new WP_Error('copy_failed', 'Failed to copy file', array('status' => 500));
            }
        }

        return new WP_REST_Response(array('message' => 'Item copied successfully'), 200);
    }

    private function copy_directory_recursive($source, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $dir = dir($source);
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            
            $source_entry = $source . '/' . $entry;
            $dest_entry = $destination . '/' . $entry;
            
            if (is_dir($source_entry)) {
                $this->copy_directory_recursive($source_entry, $dest_entry);
            } else {
                copy($source_entry, $dest_entry);
            }
        }
        
        $dir->close();
        return true;
    }

    public function list_directory_recursive($request) {
        $path = $request->get_param('path');
        
        if (empty($path)) {
            $full_path = $this->plugins_dir;
        } else {
            $full_path = $this->validate_path($path);
        }

        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        if (!is_dir($full_path)) {
            return new WP_Error('not_directory', 'Specified path is not a directory', array('status' => 400));
        }

        $items = $this->scan_directory_recursive($full_path);
        
        return new WP_REST_Response($items, 200);
    }

    private function scan_directory_recursive($dir) {
        $result = array();
        $base_path = strlen($this->plugins_dir);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
    
        foreach ($iterator as $item) {
            $relative_path = substr($item->getPathname(), $base_path + 1);
            
            // Skip hidden directories and their contents
            if (preg_match('/(^|\/)\./i', $relative_path)) {
                continue;
            }
            
            $result[] = array(
                'name' => $item->getFilename(),
                'path' => $relative_path,
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->getSize(),
                'modified' => date('Y-m-d H:i:s', $item->getMTime()),
                'depth' => $iterator->getDepth()
            );
        }
    
        return $result;
    }

    public function read_directory_recursive($request) {
        $path = $request->get_param('path');
        
        if (empty($path)) {
            $full_path = $this->plugins_dir;
        } else {
            $full_path = $this->validate_path($path);
        }

        if ($full_path === false) {
            return new WP_Error('invalid_path', 'Invalid path specified', array('status' => 400));
        }

        if (!is_dir($full_path)) {
            return new WP_Error('not_directory', 'Specified path is not a directory', array('status' => 400));
        }

        $content = $this->read_directory_contents_recursive($full_path);
        
        return new WP_REST_Response($content, 200);
    }

    private function read_directory_contents_recursive($dir) {
        $result = array();
        $base_path = strlen($this->plugins_dir);
        
        $directory_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
    
        foreach ($directory_iterator as $file_item) {
            $relative_path = substr($file_item->getPathname(), $base_path + 1);
            
            if ($file_item->isDir()) {
                $entry = array(
                    'name' => $file_item->getFilename(),
                    'path' => $relative_path,
                    'type' => 'directory',
                    'size' => $file_item->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file_item->getMTime()),
                    'depth' => $directory_iterator->getDepth(),
                    'children' => array()
                );
            } else {
                // Check if file is binary
                $finfo = finfo_open(FILEINFO_MIME);
                $mime_type = finfo_file($finfo, $file_item->getPathname());
                finfo_close($finfo);
                
                $is_text = strpos($mime_type, 'text/') === 0 || 
                          strpos($mime_type, 'application/json') !== false ||
                          strpos($mime_type, 'application/javascript') !== false ||
                          strpos($mime_type, 'application/xml') !== false;
    
                $entry = array(
                    'name' => $file_item->getFilename(),
                    'path' => $relative_path,
                    'type' => 'file',
                    'size' => $file_item->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file_item->getMTime()),
                    'depth' => $directory_iterator->getDepth(),
                    'mime_type' => $mime_type,
                    'is_binary' => !$is_text
                );
    
                // Only include content for text files
                if ($is_text) {
                    $entry['content'] = file_get_contents($file_item->getPathname());
                }
            }
    
            // Build the tree structure
            $path_parts = explode('/', $relative_path);
            $current = &$result;
            
            for ($i = 0; $i < count($path_parts) - 1; $i++) {
                foreach ($current as &$existing_item) {
                    if ($existing_item['type'] === 'directory' && $existing_item['name'] === $path_parts[$i]) {
                        $current = &$existing_item['children'];
                        break;
                    }
                }
            }
            
            $current[] = $entry;
        }
    
        return $result;
    }
}

// Initialize the API
$openkbs_filesystem_api = new OpenKBS_Filesystem_API();