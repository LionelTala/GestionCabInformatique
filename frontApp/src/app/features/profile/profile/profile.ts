// src/app/features/profile/profile/profile.ts
import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ProfileService } from '../../../core/services/profile.service';
import { Auth } from '../../../core/services/auth';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './profile.html',
  styleUrl: './profile.css'
})
export class ProfileComponent implements OnInit {
  private profileService = inject(ProfileService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  currentUser = this.auth.getUser();

  loading = signal(false);
  submittingInfo = signal(false);
  submittingPassword = signal(false);
  activeTab = signal<'info' | 'password'>('info');

  // Info form
  infoForm = signal({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
  });
  infoErrors = signal({ first_name: '', last_name: '', email: '', phone: '' });

  // Password form
  passwordForm = signal({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  passwordErrors = signal({ current_password: '', password: '', password_confirmation: '' });
  showCurrentPassword = signal(false);
  showNewPassword = signal(false);
  showConfirmPassword = signal(false);

  ngOnInit() {
    this.loadProfile();
  }

  loadProfile() {
    this.loading.set(true);
    this.profileService.getProfile().subscribe({
      next: (res) => {
        const u = res.data;
        this.infoForm.set({
          first_name: u.first_name || '',
          last_name: u.last_name || '',
          email: u.email || '',
          phone: u.phone || '',
        });
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  // ═══ Onglets ═══
  setTab(tab: 'info' | 'password') {
    this.activeTab.set(tab);
  }

  // ═══ Info ═══
  validateInfo(): boolean {
    const d = this.infoForm();
    const e = { first_name: '', last_name: '', email: '', phone: '' };
    let valid = true;

    if (!d.first_name.trim()) { e.first_name = 'Le prénom est obligatoire'; valid = false; }
    else if (d.first_name.trim().length < 2) { e.first_name = 'Minimum 2 caractères'; valid = false; }

    if (!d.last_name.trim()) { e.last_name = 'Le nom est obligatoire'; valid = false; }
    else if (d.last_name.trim().length < 2) { e.last_name = 'Minimum 2 caractères'; valid = false; }

    if (d.email && d.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email.trim())) {
      e.email = 'Email invalide';
      valid = false;
    }

    if (d.phone && d.phone.trim() && !/^[0-9+\-\s]{8,20}$/.test(d.phone.trim())) {
      e.phone = 'Numéro invalide';
      valid = false;
    }

    this.infoErrors.set(e);
    return valid;
  }

  onSubmitInfo() {
    if (!this.validateInfo()) {
      this.toastr.warning('Veuillez corriger les erreurs');
      return;
    }

    const d = this.infoForm();
    this.submittingInfo.set(true);

    this.profileService.updateProfile({
      first_name: d.first_name.trim(),
      last_name: d.last_name.trim(),
      email: d.email.trim() || null,
      phone: d.phone.trim() || null,
    }).subscribe({
      next: (res) => {
        this.toastr.success('Profil mis à jour avec succès');
        // ✅ Met à jour l'utilisateur en cache
        this.auth.updateCurrentUser(res.data);
        this.submittingInfo.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la mise à jour');
        this.submittingInfo.set(false);
      },
    });
  }

  // ═══ Password ═══
  validatePassword(): boolean {
    const d = this.passwordForm();
    const e = { current_password: '', password: '', password_confirmation: '' };
    let valid = true;

    if (!d.current_password) { e.current_password = 'Le mot de passe actuel est obligatoire'; valid = false; }

    if (!d.password) { e.password = 'Le nouveau mot de passe est obligatoire'; valid = false; }
    else if (d.password.length < 6) { e.password = 'Minimum 6 caractères'; valid = false; }

    if (!d.password_confirmation) { e.password_confirmation = 'Confirmez le mot de passe'; valid = false; }
    else if (d.password !== d.password_confirmation) { e.password_confirmation = 'Les mots de passe ne correspondent pas'; valid = false; }

    this.passwordErrors.set(e);
    return valid;
  }

  onSubmitPassword() {
    if (!this.validatePassword()) return;

    const d = this.passwordForm();
    this.submittingPassword.set(true);

    this.profileService.updatePassword({
      current_password: d.current_password,
      password: d.password,
      password_confirmation: d.password_confirmation,
    }).subscribe({
      next: () => {
        this.toastr.success('Mot de passe modifié avec succès');
        this.passwordForm.set({ current_password: '', password: '', password_confirmation: '' });
        this.passwordErrors.set({ current_password: '', password: '', password_confirmation: '' });
        this.submittingPassword.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la modification');
        this.submittingPassword.set(false);
      },
    });
  }

  // ═══ Helpers ═══
  getRoleLabel(role: string): string {
    const labels: Record<string, string> = {
      super_admin: 'Super Admin',
      admin_global: 'Admin Global',
      admin_campus: 'Admin Campus',
      secretary: 'Secrétaire',
    };
    return labels[role] || role;
  }

  getInitials(): string {
    const u = this.currentUser;
    if (!u) return '?';
    return (u.last_name?.[0] || '') + (u.first_name?.[0] || '');
  }
}