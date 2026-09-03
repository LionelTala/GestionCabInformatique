import { Component, signal, OnInit, inject, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ActivityLogService, ActivityLog } from '../../../core/services/activity-log';
import { CampusService } from '../../../core/services/campus';
import { UserService } from '../../../core/services/user';
import { Auth } from '../../../core/services/auth';
import { RegistrationService } from '../../../core/services/registration';

@Component({
  imports: [CommonModule, FormsModule, RouterModule],
  selector: 'app-logs',
  styleUrl: './logs.css',
  templateUrl: './logs.html',
})
export class Logs implements OnInit {
  private activityLogService = inject(ActivityLogService);
  private campusService = inject(CampusService);
  private userService = inject(UserService);
  private auth = inject(Auth);
  private registrationService = inject(RegistrationService);
  private toastr = inject(ToastrService);

  logs = this.activityLogService.getLogs();
  meta = this.activityLogService.getMeta();
  loading = this.activityLogService.getLoading();
  campuses = this.campusService.getCampuses();
  users = this.userService.getUsers();
  currentUser = this.auth.getUser();

  currentPage = signal(1);
  restoring = signal(false);

  // Filtres
  filters = signal({
    campus_id: null as number | null,
    action: '' as string,
    user_id: null as number | null,
  });

  // Options de filtres
  actionOptions = [
    { value: '', label: 'Toutes les actions' },
    { value: 'created', label: 'Création' },
    { value: 'updated', label: 'Modification' },
    { value: 'deleted', label: 'Suppression' },
    { value: 'restored', label: 'Restauration' },
  ];

  constructor() {
    effect(() => {
      this.loadLogs(this.currentPage());
    });
  }

  ngOnInit() {
    this.campusService.loadCampuses();
    this.userService.loadUsers();
    this.loadLogs(1);
  }

  loadLogs(page: number = 1) {
    const filters = this.filters();
    const params: any = {};

    if (filters.campus_id) params.campus_id = filters.campus_id;
    if (filters.action) params.action = filters.action;
    if (filters.user_id) params.user_id = filters.user_id;

    this.activityLogService.refresh(page, params);
  }

  applyFilters() {
    this.currentPage.set(1);
    this.loadLogs(1);
  }

  resetFilters() {
    this.filters.set({
      campus_id: null,
      action: '',
      user_id: null,
    });
    this.currentPage.set(1);
    this.loadLogs(1);
  }

  goToPage(page: number) {
    const meta = this.meta();
    if (meta && page >= 1 && page <= meta.last_page) {
      this.currentPage.set(page);
    }
  }

  getPages(): number[] {
    const meta = this.meta();
    if (!meta) return [];
    
    const total = meta.last_page;
    const current = meta.current_page;
    const pages: number[] = [];

    if (total <= 7) {
      for (let i = 1; i <= total; i++) {
        pages.push(i);
      }
    } else {
      pages.push(1);
      if (current > 3) pages.push(-1);
      for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
        pages.push(i);
      }
      if (current < total - 2) pages.push(-1);
      pages.push(total);
    }

    return pages;
  }

 

  // === UTILITAIRES ===
  getActionIcon(action: string): string {
    const icons: Record<string, string> = {
      created: 'add_circle',
      updated: 'edit',
      deleted: 'delete',
      restored: 'restore',
    };
    return icons[action] || 'info';
  }

  getActionColor(action: string): string {
    const colors: Record<string, string> = {
      created: 'text-green-600 bg-green-50',
      updated: 'text-blue-600 bg-blue-50',
      deleted: 'text-red-600 bg-red-50',
      restored: 'text-purple-600 bg-purple-50',
    };
    return colors[action] || 'text-gray-600 bg-gray-50';
  }

  getActionLabel(action: string): string {
    const labels: Record<string, string> = {
      created: 'Création',
      updated: 'Modification',
      deleted: 'Suppression',
      restored: 'Restauration',
    };
    return labels[action] || action;
  }

  getUserName(log: ActivityLog): string {
    if (log.user) {
      return log.user.first_name + ' ' + log.user.last_name;
    }
    return 'Utilisateur inconnu';
  }

  getUserRole(log: ActivityLog): string {
    const roles: Record<string, string> = {
      super_admin: 'Super Admin',
      admin_global: 'Admin Global',
      admin_campus: 'Admin Campus',
      secretary: 'Secrétaire',
    };
    return roles[log.user_role] || log.user_role;
  }

  getCampusName(log: ActivityLog): string {
    if (log.campus) {
      return log.campus.name;
    }
    if (log.campus_id) {
      const campus = this.campuses().find(c => c.id === log.campus_id);
      return campus ? campus.name : '-';
    }
    return '-';
  }

  formatDate(date: string): string {
    if (!date) return '-';
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  canSeeCampus(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  canRestore(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global', 'admin_campus'].includes(user.role);
  }
}