// src/app/features/logs/logs/logs.ts
import { Component, signal, OnInit, inject, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ActivityLogService, ActivityLog } from '../../../core/services/activity-log';
import { CampusService } from '../../../core/services/campus';
import { UserService } from '../../../core/services/user';
import { Auth } from '../../../core/services/auth';

type Period = 'today' | 'week' | 'month' | 'year' | 'custom' | 'all';

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
  private toastr = inject(ToastrService);

  logs = this.activityLogService.getLogs();
  meta = this.activityLogService.getMeta();
  loading = this.activityLogService.getLoading();
  periodMeta = this.activityLogService.getPeriodMeta();
  campuses = this.campusService.getCampuses();
  users = this.userService.getUsers();
  currentUser = this.auth.getUser();

  currentPage = signal(1);

  // ═══ FILTRES ═══
  filters = signal({
    campus_id: null as number | null,
    action: '' as string,
    user_id: null as number | null,
    target_type: '' as string,
    period: 'today' as Period,
    date_from: '',
    date_to: '',
  });

  // ═══ OPTIONS ═══
  periodOptions: { key: Period; label: string; short: string; icon: string }[] = [
    { key: 'today', label: "Aujourd'hui", short: "Auj.", icon: 'today' },
    { key: 'week', label: 'Cette semaine', short: 'Sem.', icon: 'date_range' },
    { key: 'month', label: 'Ce mois', short: 'Mois', icon: 'calendar_month' },
    { key: 'year', label: 'Cette année', short: 'Année', icon: 'calendar_today' },
    { key: 'custom', label: 'Personnalisé', short: 'Perso', icon: 'edit_calendar' },
    { key: 'all', label: 'Tout', short: 'Tout', icon: 'all_inclusive' },
  ];

  actionOptions = [
    { value: '', label: 'Toutes les actions' },
    { value: 'created', label: 'Création' },
    { value: 'updated', label: 'Modification' },
    { value: 'deleted', label: 'Suppression' },
    { value: 'restored', label: 'Restauration' },
  ];

  targetTypeOptions = [
    { value: '', label: 'Toutes les ressources' },
    { value: 'registration', label: 'Inscriptions' },
    { value: 'payment', label: 'Paiements' },
    { value: 'cash_movement', label: 'Caisse' },
    { value: 'student', label: 'Étudiants' },
    { value: 'expense', label: 'Dépenses' },
    { value: 'user', label: 'Utilisateurs' },
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

  // ═══ CHARGEMENT ═══
  loadLogs(page: number = 1) {
    const filters = this.filters();
    const params: any = { period: filters.period };

    if (filters.campus_id) params.campus_id = filters.campus_id;
    if (filters.action) params.action = filters.action;
    if (filters.user_id) params.user_id = filters.user_id;
    if (filters.target_type) params.target_type = filters.target_type;
    if (filters.period === 'custom') {
      if (filters.date_from) params.date_from = filters.date_from;
      if (filters.date_to)   params.date_to = filters.date_to;
    }

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
      target_type: '',
      period: 'today',
      date_from: '',
      date_to: '',
    });
    this.currentPage.set(1);
    this.loadLogs(1);
  }

  // ═══ PÉRIODE ═══
  setPeriod(period: Period) {
    this.filters.update(f => ({ ...f, period }));
    this.currentPage.set(1);
    this.loadLogs(1);
  }

  getPeriodLabel(): string {
    const labels: Record<Period, string> = {
      today: "Aujourd'hui",
      week: 'Cette semaine',
      month: 'Ce mois',
      year: 'Cette année',
      custom: 'Personnalisée',
      all: 'Tout',
    };
    return labels[this.filters().period] || "Aujourd'hui";
  }

  // ═══ PAGINATION ═══
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
      for (let i = 1; i <= total; i++) pages.push(i);
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

  // ═══════════════════════════════════════════════════════════
  // ✅ ICÔNES ET COULEURS PAR ACTION + TYPE DE RESSOURCE
  // ═══════════════════════════════════════════════════════════

  private detectCashMovementType(log: ActivityLog): 'income' | 'expense' | null {
    if (log.target_type !== 'cash_movement') return null;
    const changes = (log.changes || '').toLowerCase();
    if (changes.includes('entrée')) return 'income';
    if (changes.includes('sortie')) return 'expense';
    return null;
  }

  getActionIcon(log: ActivityLog): string {
    const action = log.action;
    const targetType = log.target_type;

    // ✅ Mouvements de caisse
    if (targetType === 'cash_movement') {
      const cashType = this.detectCashMovementType(log);
      if (cashType === 'income') return 'add_circle';
      if (cashType === 'expense') return 'remove_circle';
      if (action === 'deleted') return 'delete_forever';
      return 'point_of_sale';
    }

    // Par type de ressource
    const iconsByTarget: Record<string, string> = {
      registration: action === 'created' ? 'person_add' : 'assignment',
      payment: action === 'deleted' ? 'cancel' : 'payments',
      student: 'school',
      expense: 'receipt_long',
      user: 'person',
    };
    if (iconsByTarget[targetType]) return iconsByTarget[targetType];

    // Par action
    const iconsByAction: Record<string, string> = {
      created: 'add_circle',
      updated: 'edit',
      deleted: 'delete',
      restored: 'restore',
    };
    return iconsByAction[action] || 'info';
  }

  getActionColor(log: ActivityLog): string {
    const action = log.action;
    const targetType = log.target_type;

    // ✅ Mouvements de caisse
    if (targetType === 'cash_movement') {
      const cashType = this.detectCashMovementType(log);
      if (action === 'deleted') return 'text-red-700 bg-red-50';
      if (cashType === 'income') return 'text-emerald-700 bg-emerald-50';
      if (cashType === 'expense') return 'text-rose-700 bg-rose-50';
      return 'text-amber-700 bg-amber-50';
    }

    const colors: Record<string, string> = {
      created: 'text-green-600 bg-green-50',
      updated: 'text-blue-600 bg-blue-50',
      deleted: 'text-red-600 bg-red-50',
      restored: 'text-purple-600 bg-purple-50',
    };
    return colors[action] || 'text-gray-600 bg-gray-50';
  }

  getActionBadgeColor(log: ActivityLog): string {
    const action = log.action;
    const targetType = log.target_type;

    if (targetType === 'cash_movement') {
      const cashType = this.detectCashMovementType(log);
      if (action === 'deleted') return 'bg-red-100 text-red-700';
      if (cashType === 'income') return 'bg-emerald-100 text-emerald-700';
      if (cashType === 'expense') return 'bg-rose-100 text-rose-700';
      return 'bg-amber-100 text-amber-700';
    }

    const colors: Record<string, string> = {
      created: 'bg-green-100 text-green-700',
      updated: 'bg-blue-100 text-blue-700',
      deleted: 'bg-red-100 text-red-700',
      restored: 'bg-purple-100 text-purple-700',
    };
    return colors[action] || 'bg-gray-100 text-gray-700';
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

  getFullActionLabel(log: ActivityLog): string {
    const action = this.getActionLabel(log.action);

    if (log.target_type === 'cash_movement') {
      const cashType = this.detectCashMovementType(log);
      if (cashType === 'income') return `${action} — Entrée caisse`;
      if (cashType === 'expense') return `${action} — Sortie caisse`;
      return `${action} — Caisse`;
    }

    const targetLabels: Record<string, string> = {
      registration: 'Inscription',
      payment: 'Paiement',
      student: 'Étudiant',
      expense: 'Dépense',
      user: 'Utilisateur',
    };
    const target = targetLabels[log.target_type] || log.target_type;
    return `${action} — ${target}`;
  }

  // ═══ UTILITAIRES ═══
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
    if (log.campus) return log.campus.name;
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
      minute: '2-digit',
    });
  }

  canSeeCampus(): boolean {
    const user = this.currentUser;
    return !!user && ['super_admin', 'admin_global'].includes(user.role);
  }
}