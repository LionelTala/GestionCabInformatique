import { Component, signal, OnInit, inject, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { UserService, User } from '../../../core/services/user';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

type FormErrors = Partial<{
  first_name: string; last_name: string; email: string;
  phone: string; password: string; campus_id: string;
}>;

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-users',
  styleUrl: './users.css',
  templateUrl: './users.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Users implements OnInit {
  private userService = inject(UserService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  users = this.userService.getUsers();
  meta = this.userService.getMeta();
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  loading = signal(false);
  submitting = signal(false);
  togglingId = signal<number | null>(null);
  currentPage = signal(1);
  searchTerm = signal('');

  showModal = signal(false);
  isEditing = signal(false);
  selectedUser = signal<User | null>(null);

  showDeleteModal = signal(false);
  userToDelete = signal<User | null>(null);

  errors = signal<FormErrors>({});

  availableRoles = computed(() => {
    const role = this.currentUser?.role;
    if (!role) return [];
    if (role === 'super_admin' || role === 'admin_global') {
      return [
        { value: 'admin_global', label: 'Admin Global' },
        { value: 'admin_campus', label: 'Admin Campus' },
        { value: 'secretary', label: 'Secrétaire' },
      ];
    }
    if (role === 'admin_campus') {
      return [{ value: 'secretary', label: 'Secrétaire' }];
    }
    return [];
  });

  pages = computed(() => {
    const m = this.meta();
    const current = m.current_page;
    const last = m.last_page;
    let start = Math.max(1, current - 2);
    let end = Math.min(last, start + 4);
    if (end - start < 4) start = Math.max(1, end - 4);
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
  });

  formData = signal({
    first_name: '', last_name: '', email: '', phone: '',
    role: 'secretary', campus_id: null as number | null,
    password: '', is_active: true
  });

  ngOnInit() {
    this.loadUsers();
    this.campusService.loadCampuses();
  }

  loadUsers() {
    this.loading.set(true);
    const params: Record<string, string> = {};
if (this.searchTerm().trim()) params['search'] = this.searchTerm().trim();    this.userService.loadUsers(this.currentPage(), params).subscribe({
      complete: () => this.loading.set(false),
      error: () => this.loading.set(false)
    });
  }

  changePage(page: number) {
    this.currentPage.set(page);
    this.loadUsers();
  }

  onSearch() {
    this.currentPage.set(1);
    this.loadUsers();
  }

  validate(): boolean {
    const d = this.formData();
    const e: FormErrors = {};

    if (!d.first_name.trim()) e.first_name = 'Le prénom est obligatoire';
    else if (d.first_name.trim().length < 2) e.first_name = 'Minimum 2 caractères';

    if (!d.last_name.trim()) e.last_name = 'Le nom est obligatoire';
    else if (d.last_name.trim().length < 2) e.last_name = 'Minimum 2 caractères';

    if (!d.email.trim()) e.email = "L'email est obligatoire";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email.trim())) e.email = 'Email invalide';

    if (d.phone.trim() && !/^[0-9+\-\s]{8,20}$/.test(d.phone.trim())) {
      e.phone = 'Numéro invalide';
    }

    if (!this.isEditing() && !d.password) e.password = 'Le mot de passe est obligatoire';
    else if (d.password && d.password.length < 6) e.password = 'Minimum 6 caractères';

    if (['admin_campus', 'secretary'].includes(d.role) && !d.campus_id) {
      e.campus_id = 'Le campus est obligatoire pour ce rôle';
    }

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  openCreateModal() {
    this.isEditing.set(false);
    this.selectedUser.set(null);
    this.errors.set({});
    this.formData.set({
      first_name: '', last_name: '', email: '', phone: '',
      role: 'secretary', campus_id: null, password: '', is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(user: User) {
    this.isEditing.set(true);
    this.selectedUser.set(user);
    this.errors.set({});
    this.formData.set({
      first_name: user.first_name, last_name: user.last_name,
      email: user.email, phone: user.phone || '',
      role: user.role, campus_id: user.campus_id || null,
      password: '', is_active: user.is_active
    });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({});
  }

  onSubmit() {
    if (!this.validate()) return;

    this.submitting.set(true);
    const d = this.formData();
    const data: any = { first_name: d.first_name, last_name: d.last_name, email: d.email, phone: d.phone, is_active: d.is_active };

    if (this.isEditing()) {
      if (d.role) data.role = d.role;
      if (d.campus_id) data.campus_id = d.campus_id;
      if (d.password) data.password = d.password;

      this.userService.update(this.selectedUser()!.id, data).subscribe({
        next: () => { this.toastr.success('Utilisateur modifié'); this.closeModal(); this.loadUsers(); this.submitting.set(false); },
        error: (err: any) => this.handleError(err)
      });
    } else {
      data.role = d.role;
      if (d.campus_id) data.campus_id = d.campus_id;
      data.password = d.password;

      this.userService.create(data).subscribe({
        next: () => { this.toastr.success('Utilisateur créé'); this.closeModal(); this.loadUsers(); this.submitting.set(false); },
        error: (err: any) => this.handleError(err)
      });
    }
  }

  private handleError(err: any) {
    if (err.status === 422 && err.error?.errors) {
      const e: FormErrors = {};
      for (const [key, msg] of Object.entries(err.error.errors)) {
        if (['first_name', 'last_name', 'email', 'phone', 'password', 'campus_id'].includes(key)) {
          e[key as keyof FormErrors] = Array.isArray(msg) ? msg[0] : msg as string;
        }
      }
      this.errors.set(e);
    } else {
      this.toastr.error(err.error?.message || 'Erreur');
    }
    this.submitting.set(false);
  }

  openDeleteModal(user: User) { this.userToDelete.set(user); this.showDeleteModal.set(true); }
  closeDeleteModal() { this.showDeleteModal.set(false); this.userToDelete.set(null); }

  confirmDelete() {
    const user = this.userToDelete();
    if (!user) return;
    this.submitting.set(true);
    this.userService.delete(user.id).subscribe({
      next: () => { this.toastr.success('Utilisateur supprimé'); this.closeDeleteModal(); this.loadUsers(); this.submitting.set(false); },
      error: () => this.submitting.set(false)
    });
  }

  toggleStatus(user: User) {
    this.togglingId.set(user.id);
    this.userService.toggleStatus(user.id).subscribe({
      next: () => this.loadUsers(),
      error: () => this.togglingId.set(null),
      complete: () => this.togglingId.set(null)
    });
  }

  getRoleLabel(role: string): string {
    return { super_admin: 'Super Admin', admin_global: 'Admin Global', admin_campus: 'Admin Campus', secretary: 'Secrétaire' }[role] || role;
  }
}