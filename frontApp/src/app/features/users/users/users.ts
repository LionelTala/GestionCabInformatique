import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { UserService, User } from '../../../core/services/user';
import { CampusService, Campus } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

type UserRole = 'super_admin' | 'admin_global' | 'admin_campus' | 'secretary';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-users',
  styleUrl: './users.css',
  templateUrl: './users.html',
})
export class Users implements OnInit {
  private userService = inject(UserService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  users = this.userService.getUsers();
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  loading = signal(true);
  submitting = signal(false);

  errors = signal({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    campus_id: '',
  });

  availableRoles = computed(() => {
    const user = this.currentUser;
    if (!user) return [];

    switch (user.role) {
      case 'super_admin':
        return [
          { value: 'admin_global' as UserRole, label: 'Admin Global' },
          { value: 'admin_campus' as UserRole, label: 'Admin Campus' },
          { value: 'secretary' as UserRole, label: 'Secrétaire' },
        ];
      case 'admin_global':
        return [
          { value: 'admin_global' as UserRole, label: 'Admin Global' },
          { value: 'admin_campus' as UserRole, label: 'Admin Campus' },
          { value: 'secretary' as UserRole, label: 'Secrétaire' },
        ];
      case 'admin_campus':
        return [
          { value: 'secretary' as UserRole, label: 'Secrétaire' },
        ];
      default:
        return [];
    }
  });

  showModal = signal(false);
  isEditing = signal(false);
  selectedUser = signal<User | null>(null);

  formData = signal({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: '' as string,
    campus_id: null as number | null,
    password: '',
    is_active: true
  });

  ngOnInit() {
    this.loading.set(true);
    this.userService.loadUsers();
    this.campusService.loadCampuses();
    this.loading.set(false);
  }

  // === VALIDATIONS ===
  validateFirstName(): boolean {
    const value = this.formData().first_name.trim();
    if (!value) {
      this.errors.update(e => ({ ...e, first_name: 'Le prénom est obligatoire' }));
      return false;
    }
    if (value.length < 2) {
      this.errors.update(e => ({ ...e, first_name: 'Le prénom doit contenir au moins 2 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, first_name: '' }));
    return true;
  }

  validateLastName(): boolean {
    const value = this.formData().last_name.trim();
    if (!value) {
      this.errors.update(e => ({ ...e, last_name: 'Le nom est obligatoire' }));
      return false;
    }
    if (value.length < 2) {
      this.errors.update(e => ({ ...e, last_name: 'Le nom doit contenir au moins 2 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, last_name: '' }));
    return true;
  }

  validateEmail(): boolean {
    const value = this.formData().email.trim();
    if (!value) {
      this.errors.update(e => ({ ...e, email: 'L\'email est obligatoire' }));
      return false;
    }
    if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value)) {
      this.errors.update(e => ({ ...e, email: 'Veuillez saisir un email valide' }));
      return false;
    }
    this.errors.update(e => ({ ...e, email: '' }));
    return true;
  }

  validatePhone(): boolean {
    const value = this.formData().phone.trim();
    if (value && !/^[0-9+\-\s]{8,20}$/.test(value)) {
      this.errors.update(e => ({ ...e, phone: 'Numéro invalide (8-20 caractères)' }));
      return false;
    }
    this.errors.update(e => ({ ...e, phone: '' }));
    return true;
  }

  validatePassword(): boolean {
    if (this.isEditing()) {
      this.errors.update(e => ({ ...e, password: '' }));
      return true;
    }
    const value = this.formData().password;
    if (!value) {
      this.errors.update(e => ({ ...e, password: 'Le mot de passe est obligatoire' }));
      return false;
    }
    if (value.length < 6) {
      this.errors.update(e => ({ ...e, password: 'Le mot de passe doit contenir au moins 6 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, password: '' }));
    return true;
  }

  validateCampus(): boolean {
    const role = this.formData().role;
    if (['admin_campus', 'secretary'].includes(role) && !this.formData().campus_id) {
      this.errors.update(e => ({ ...e, campus_id: 'Le campus est obligatoire pour ce rôle' }));
      return false;
    }
    this.errors.update(e => ({ ...e, campus_id: '' }));
    return true;
  }

  validateForm(): boolean {
    const isFirstNameValid = this.validateFirstName();
    const isLastNameValid = this.validateLastName();
    const isEmailValid = this.validateEmail();
    const isPhoneValid = this.validatePhone();
    const isPasswordValid = this.validatePassword();
    const isCampusValid = this.validateCampus();
    return isFirstNameValid && isLastNameValid && isEmailValid && isPhoneValid && isPasswordValid && isCampusValid;
  }

  // === MODAL ===
  openCreateModal() {
    this.isEditing.set(false);
    this.selectedUser.set(null);
    this.errors.set({ first_name: '', last_name: '', email: '', phone: '', password: '', campus_id: '' });
    this.formData.set({
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      role: 'secretary',
      campus_id: null,
      password: '',
      is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(user: User) {
    this.isEditing.set(true);
    this.selectedUser.set(user);
    this.errors.set({ first_name: '', last_name: '', email: '', phone: '', password: '', campus_id: '' });

    // Garder le rôle actuel de l'utilisateur
    const currentRole = user.role;

    this.formData.set({
      first_name: user.first_name,
      last_name: user.last_name,
      email: user.email,
      phone: user.phone || '',
      role: currentRole,
      campus_id: user.campus_id || null,
      password: '',
      is_active: user.is_active
    });

    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ first_name: '', last_name: '', email: '', phone: '', password: '', campus_id: '' });
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    this.submitting.set(true);

    if (this.isEditing() && this.selectedUser()) {
      const updateData: any = {
        first_name: data.first_name,
        last_name: data.last_name,
        email: data.email,
        phone: data.phone,
        is_active: data.is_active
      };

      // Ajouter le rôle seulement s'il est présent et différent
      if (data.role) {
        updateData.role = data.role as UserRole;
      }

      // Ajouter campus_id si présent
      if (data.campus_id) {
        updateData.campus_id = data.campus_id;
      }

      if (data.password) {
        updateData.password = data.password;
      }

      this.userService.update(this.selectedUser()!.id, updateData).subscribe({
        next: () => {
          this.toastr.success('Utilisateur modifié avec succès');
          this.closeModal();
          this.userService.refresh();
          this.submitting.set(false);
        },
        error: (err) => {
          const message = err.error?.message || 'Erreur lors de la modification';
          this.toastr.error(message);
          this.submitting.set(false);
        }
      });
    } else {
      const createData: any = {
        first_name: data.first_name,
        last_name: data.last_name,
        email: data.email,
        phone: data.phone,
        role: data.role as UserRole,
        is_active: data.is_active,
        password: data.password
      };

      if (data.campus_id) {
        createData.campus_id = data.campus_id;
      }

      this.userService.create(createData).subscribe({
        next: () => {
          this.toastr.success('Utilisateur créé avec succès');
          this.closeModal();
          this.userService.refresh();
          this.submitting.set(false);
        },
        error: (err) => {
          const message = err.error?.message || 'Erreur lors de la création';
          this.toastr.error(message);
          this.submitting.set(false);
        }
      });
    }
  }

  toggleStatus(user: User) {
    this.userService.toggleStatus(user.id).subscribe({
      next: () => {
        const status = !user.is_active ? 'activé' : 'désactivé';
        this.toastr.success(`Utilisateur ${status} avec succès`);
        this.userService.refresh();
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du changement de statut');
      }
    });
  }

  deleteUser(user: User) {
    if (!confirm(`Voulez-vous vraiment supprimer l'utilisateur "${user.first_name} ${user.last_name}" ?`)) {
      return;
    }

    this.userService.delete(user.id).subscribe({
      next: () => {
        this.toastr.success('Utilisateur supprimé avec succès');
        this.userService.refresh();
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
      }
    });
  }

  getRoleLabel(role: string): string {
    const labels: Record<string, string> = {
      super_admin: 'Super Admin',
      admin_global: 'Admin Global',
      admin_campus: 'Admin Campus',
      secretary: 'Secrétaire'
    };
    return labels[role] || role;
  }

  getCampusName(campusId: number | undefined): string {
    if (!campusId) return '-';
    const campus = this.campuses().find(c => c.id === campusId);
    return campus ? campus.name : '-';
  }
}