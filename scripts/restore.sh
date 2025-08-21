#!/bin/bash

# EspoCRM Restore Script for Dokploy Deployment
# This script restores database and files from backup
# Usage: ./restore.sh <backup_name> [backup_dir]

set -e

# Configuration
BACKUP_NAME="${1}"
BACKUP_DIR="${2:-/backup}"

# Database configuration from environment
DB_HOST="${DB_HOST:-database}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-espocrm}"
DB_USER="${DB_USER:-espocrm}"
DB_PASSWORD="${DB_PASSWORD}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_prompt() {
    echo -e "${BLUE}[PROMPT]${NC} $1"
}

show_usage() {
    cat <<EOF
Usage: $0 <backup_name> [backup_dir]

Restore EspoCRM from backup

Arguments:
  backup_name    Name of the backup to restore (without file extensions)
                 Example: espocrm_backup_20240121_120000
  backup_dir     Directory containing backups (default: /backup)

Examples:
  $0 espocrm_backup_20240121_120000
  $0 espocrm_backup_20240121_120000 /custom/backup/path

To list available backups:
  $0 --list

EOF
}

list_backups() {
    log_info "Available backups in $BACKUP_DIR:"
    echo ""
    
    local count=0
    for manifest in "$BACKUP_DIR"/*_manifest.txt; do
        if [ -f "$manifest" ]; then
            local backup_name=$(basename "$manifest" "_manifest.txt")
            local backup_date=$(grep "Timestamp:" "$manifest" | cut -d: -f2-)
            local db_file="${BACKUP_DIR}/${backup_name}_database.sql.gz"
            local files_archive="${BACKUP_DIR}/${backup_name}_files.tar.gz"
            
            echo "  📦 $backup_name"
            echo "     Date: $backup_date"
            
            if [ -f "$db_file" ]; then
                local db_size=$(du -h "$db_file" | cut -f1)
                echo "     Database: ✅ ($db_size)"
            else
                echo "     Database: ❌ (missing)"
            fi
            
            if [ -f "$files_archive" ]; then
                local files_size=$(du -h "$files_archive" | cut -f1)
                echo "     Files: ✅ ($files_size)"
            else
                echo "     Files: ⚠️  (missing)"
            fi
            echo ""
            ((count++))
        fi
    done
    
    if [ $count -eq 0 ]; then
        log_warning "No backups found in $BACKUP_DIR"
    else
        log_info "Total backups found: $count"
    fi
}

check_backup_files() {
    log_info "Checking backup files..."
    
    local db_backup="${BACKUP_DIR}/${BACKUP_NAME}_database.sql.gz"
    local file_backup="${BACKUP_DIR}/${BACKUP_NAME}_files.tar.gz"
    local manifest="${BACKUP_DIR}/${BACKUP_NAME}_manifest.txt"
    
    if [ ! -f "$manifest" ]; then
        log_error "Manifest file not found: $manifest"
        return 1
    fi
    
    if [ ! -f "$db_backup" ]; then
        log_error "Database backup not found: $db_backup"
        return 1
    fi
    
    # File backup is optional
    if [ ! -f "$file_backup" ]; then
        log_warning "File backup not found: $file_backup (will skip file restoration)"
    fi
    
    log_info "Backup files verified"
    return 0
}

create_restore_point() {
    log_info "Creating restore point before restoration..."
    
    local restore_point_name="restore_point_$(date +%Y%m%d_%H%M%S)"
    local restore_point_dir="${BACKUP_DIR}/restore_points"
    
    mkdir -p "$restore_point_dir"
    
    # Quick backup of current state
    log_info "Backing up current database state..."
    mysqldump \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-table \
        --no-tablespaces \
        "$DB_NAME" | gzip -9 > "${restore_point_dir}/${restore_point_name}_database.sql.gz" 2>/dev/null || true
    
    log_info "Restore point created: ${restore_point_name}"
    echo "$restore_point_name" > "${BACKUP_DIR}/.last_restore_point"
}

restore_database() {
    log_info "Restoring database..."
    
    local db_backup="${BACKUP_DIR}/${BACKUP_NAME}_database.sql.gz"
    
    # Check database connection
    if ! mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" --silent; then
        log_error "Cannot connect to database"
        return 1
    fi
    
    log_info "Dropping existing database..."
    mysql \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASSWORD" \
        -e "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    log_info "Importing database backup..."
    if gunzip < "$db_backup" | mysql \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASSWORD" \
        "$DB_NAME"; then
        
        log_info "Database restored successfully"
        return 0
    else
        log_error "Database restoration failed"
        return 1
    fi
}

restore_files() {
    log_info "Restoring files..."
    
    local file_backup="${BACKUP_DIR}/${BACKUP_NAME}_files.tar.gz"
    
    if [ ! -f "$file_backup" ]; then
        log_warning "File backup not found, skipping file restoration"
        return 0
    fi
    
    # Create backup of current files
    log_info "Backing up current files..."
    local current_backup="/tmp/current_files_$(date +%Y%m%d_%H%M%S).tar.gz"
    tar -czf "$current_backup" \
        /var/www/html/data \
        /var/www/html/custom \
        /var/www/html/client/custom \
        /var/www/html/upload 2>/dev/null || true
    
    log_info "Extracting files from backup..."
    if tar -xzf "$file_backup" -C /; then
        
        # Fix permissions
        log_info "Setting correct permissions..."
        chown -R www-data:www-data /var/www/html/data
        chown -R www-data:www-data /var/www/html/custom
        chown -R www-data:www-data /var/www/html/client/custom
        chown -R www-data:www-data /var/www/html/upload
        
        chmod -R 775 /var/www/html/data
        chmod -R 775 /var/www/html/custom
        chmod -R 775 /var/www/html/upload
        
        log_info "Files restored successfully"
        
        # Clean up temporary backup
        rm -f "$current_backup"
        
        return 0
    else
        log_error "File restoration failed"
        
        # Attempt to restore from temporary backup
        if [ -f "$current_backup" ]; then
            log_warning "Attempting to restore original files..."
            tar -xzf "$current_backup" -C / 2>/dev/null || true
            rm -f "$current_backup"
        fi
        
        return 1
    fi
}

run_post_restore() {
    log_info "Running post-restore tasks..."
    
    # Clear cache
    log_info "Clearing application cache..."
    rm -rf /var/www/html/data/cache/* 2>/dev/null || true
    rm -rf /var/www/html/data/tmp/* 2>/dev/null || true
    
    # Run rebuild command if available
    if [ -f "/var/www/html/bin/command" ]; then
        log_info "Running EspoCRM rebuild..."
        cd /var/www/html
        su -s /bin/bash -c "php bin/command rebuild" www-data || true
        
        log_info "Clearing EspoCRM cache..."
        su -s /bin/bash -c "php bin/command clear-cache" www-data || true
    fi
    
    log_info "Post-restore tasks completed"
}

confirm_restore() {
    log_warning "⚠️  WARNING: This will replace all current data!"
    echo ""
    log_info "Backup to restore: ${BACKUP_NAME}"
    
    if [ -f "${BACKUP_DIR}/${BACKUP_NAME}_manifest.txt" ]; then
        echo ""
        echo "Backup details:"
        grep -E "Timestamp:|Database:|Host:" "${BACKUP_DIR}/${BACKUP_NAME}_manifest.txt" | sed 's/^/  /'
    fi
    
    echo ""
    log_prompt "Are you sure you want to proceed? (yes/no): "
    read -r confirmation
    
    if [ "$confirmation" != "yes" ]; then
        log_info "Restoration cancelled"
        exit 0
    fi
}

rollback_restore() {
    log_error "Restoration failed! Attempting rollback..."
    
    if [ -f "${BACKUP_DIR}/.last_restore_point" ]; then
        local restore_point=$(cat "${BACKUP_DIR}/.last_restore_point")
        local restore_point_dir="${BACKUP_DIR}/restore_points"
        local restore_db="${restore_point_dir}/${restore_point}_database.sql.gz"
        
        if [ -f "$restore_db" ]; then
            log_info "Rolling back to restore point: $restore_point"
            
            gunzip < "$restore_db" | mysql \
                --host="$DB_HOST" \
                --port="$DB_PORT" \
                --user="$DB_USER" \
                --password="$DB_PASSWORD" \
                "$DB_NAME" 2>/dev/null && \
            log_info "Rollback completed" || \
            log_error "Rollback failed"
        fi
    fi
}

# Main execution
main() {
    # Check for --list option
    if [ "$1" == "--list" ] || [ "$1" == "-l" ]; then
        list_backups
        exit 0
    fi
    
    # Check arguments
    if [ -z "$BACKUP_NAME" ]; then
        show_usage
        exit 1
    fi
    
    log_info "===== EspoCRM Restore Script Started ====="
    
    # Check backup files exist
    if ! check_backup_files; then
        log_error "Backup verification failed"
        exit 1
    fi
    
    # Confirm restoration
    confirm_restore
    
    # Create restore point
    create_restore_point
    
    # Initialize status
    local restore_status="SUCCESS"
    
    # Restore database
    if restore_database; then
        log_info "✅ Database restoration completed"
    else
        restore_status="FAILED"
        log_error "❌ Database restoration failed"
        rollback_restore
        exit 1
    fi
    
    # Restore files
    if restore_files; then
        log_info "✅ File restoration completed"
    else
        log_warning "⚠️  File restoration skipped or failed"
    fi
    
    # Run post-restore tasks
    run_post_restore
    
    # Clean up restore points older than 7 days
    find "${BACKUP_DIR}/restore_points" -name "*.sql.gz" -mtime +7 -delete 2>/dev/null || true
    
    # Final status
    if [ "$restore_status" == "SUCCESS" ]; then
        log_info "===== Restoration completed successfully ====="
        log_info "Please verify the application is working correctly"
        exit 0
    else
        log_error "===== Restoration failed ====="
        exit 1
    fi
}

# Run main function
main "$@"