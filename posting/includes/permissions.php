<?php
// permissions.php

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isUser() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';
}

function getUserZoneId() {
    return $_SESSION['user_zone_id'] ?? null;
}

function getUserCommandId() {
    return $_SESSION['user_command_id'] ?? null;
}

function canViewPosting($posting_zone_id, $posting_command_id = null) {
    if (isSuperAdmin()) {
        return true;
    }
    
    if (isAdmin()) {
        $user_zone_id = getUserZoneId();
        $user_command_id = getUserCommandId();
        
        // Admin can view postings in their zone
        if ($posting_zone_id == $user_zone_id) {
            // If admin has command assignment, they can only view their command's postings
            if ($user_command_id) {
                return $posting_command_id == $user_command_id;
            }
            return true;
        }
    }
    
    if (isUser()) {
        // Users can only view postings assigned to them
        // You'll need to implement this based on your assignment logic
        return false;
    }
    
    return false;
}

function canConfirmPosting($posting_zone_id, $posting_command_id = null) {
    if (isSuperAdmin()) {
        return true;
    }
    
    if (isAdmin()) {
        return canViewPosting($posting_zone_id, $posting_command_id);
    }
    
    return false;
}
?>