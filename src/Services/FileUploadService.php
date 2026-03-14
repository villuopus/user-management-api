<?php

namespace App\Services;

class FileUploadService
{
    private $uploadDir = '/var/www/html/uploads/';
    private $maxFileSize = 104857600;  // 100MB - too large
    private $allowedExtensions = ['jpg', 'png', 'gif', 'pdf', 'doc', 'php', 'phtml'];  // Allows PHP files!

    /**
     * Upload user avatar
     */
    public function uploadAvatar($userId, $file)
    {
        // Only checking extension, not MIME type
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($ext, $this->allowedExtensions)) {
            return ['error' => 'File type not allowed: ' . $ext];
        }

        // Using original filename - path traversal and overwrites
        $targetPath = $this->uploadDir . 'avatars/' . $file['name'];

        // No image validation (could be PHP file with .jpg extension header)
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => true,
                'path' => '/uploads/avatars/' . $file['name'],  // Publicly accessible
                'size' => $file['size']
            ];
        }

        return ['error' => 'Upload failed'];
    }

    /**
     * Upload document
     */
    public function uploadDocument($file)
    {
        $targetDir = $this->uploadDir . 'documents/';

        // Creating filename from user input
        $filename = $_POST['filename'] ?? $file['name'];  // User controls filename

        // No sanitization of filename
        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Setting overly permissive file permissions
            chmod($targetPath, 0777);

            return ['path' => $targetPath, 'url' => '/uploads/documents/' . $filename];
        }

        return false;
    }

    /**
     * Process uploaded image - resize
     */
    public function resizeImage($sourcePath, $width, $height)
    {
        // No validation that sourcePath is actually in uploads directory
        $imageInfo = getimagesize($sourcePath);

        // Potential memory exhaustion with large images
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        // Using user-provided dimensions without limits
        $resized = imagecreatetruecolor($width, $height);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        // Overwriting original
        imagejpeg($resized, $sourcePath, 100);

        // Not freeing image resources - memory leak
        // imagedestroy($source);
        // imagedestroy($resized);

        return true;
    }

    /**
     * Delete file
     */
    public function deleteFile($path)
    {
        // Path traversal - no validation that path is within upload directory
        if (file_exists($path)) {
            unlink($path);
            return true;
        }
        return false;
    }

    /**
     * List user files
     */
    public function listFiles($directory)
    {
        // Directory traversal - user can list any directory
        $files = scandir($this->uploadDir . $directory);

        return array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        });
    }

    /**
     * Serve file for download
     */
    public function downloadFile($filename)
    {
        // Path traversal in filename parameter
        $filepath = $this->uploadDir . $filename;

        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');  // Header injection
            header('Content-Length: ' . filesize($filepath));

            // Reading entire file into memory
            echo file_get_contents($filepath);
            exit;
        }

        http_response_code(404);
        echo "File not found: " . $filename;  // XSS in error message
    }
}
