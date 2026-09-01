import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CampusService, Campus as CampusType } from '../../../core/services/campus';

@Component({
  imports: [CommonModule, RouterModule, FormsModule],
  selector: 'app-campus',
  styleUrl: './campus.css',
  templateUrl: './campus.html',
})
export class CampusManagement implements OnInit {
  private campusService = inject(CampusService);
  private toastr = inject(ToastrService);

  campuses = this.campusService.getCampuses();
  loading = signal(true);
  submitting = signal(false);

  // Modal principal
  showModal = signal(false);
  isEditing = signal(false);
  selectedCampus = signal<CampusType | null>(null);

  // Modal de confirmation
  showDeleteModal = signal(false);
  campusToDelete = signal<CampusType | null>(null);

  errors = signal({
    name: '',
    city: '',
    email: '',
    phone: '',
  });

  formData = signal({
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    is_active: true
  });

  ngOnInit() {
    this.loading.set(true);
    this.campusService.loadCampuses();
    this.loading.set(false);
  }

  loadCampuses() {
    this.loading.set(true);
    this.campusService.loadCampuses();
    this.loading.set(false);
  }

  // === VALIDATIONS ===
  validateName(): boolean {
    const name = this.formData().name.trim();
    if (!name) {
      this.errors.update(e => ({ ...e, name: 'Le nom du campus est obligatoire' }));
      return false;
    }
    if (name.length < 2) {
      this.errors.update(e => ({ ...e, name: 'Le nom doit contenir au moins 2 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, name: '' }));
    return true;
  }

  validateCity(): boolean {
    const city = this.formData().city.trim();
    if (!city) {
      this.errors.update(e => ({ ...e, city: 'La ville est obligatoire' }));
      return false;
    }
    if (city.length < 2) {
      this.errors.update(e => ({ ...e, city: 'La ville doit contenir au moins 2 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, city: '' }));
    return true;
  }

  validateEmail(): boolean {
    const email = this.formData().email.trim();
    if (email && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)) {
      this.errors.update(e => ({ ...e, email: 'Veuillez saisir un email valide' }));
      return false;
    }
    this.errors.update(e => ({ ...e, email: '' }));
    return true;
  }

  validatePhone(): boolean {
    const phone = this.formData().phone.trim();
    if (phone && !/^[0-9+\-\s]{8,20}$/.test(phone)) {
      this.errors.update(e => ({ ...e, phone: 'Veuillez saisir un numéro valide (8-20 caractères)' }));
      return false;
    }
    this.errors.update(e => ({ ...e, phone: '' }));
    return true;
  }

  validateForm(): boolean {
    const isNameValid = this.validateName();
    const isCityValid = this.validateCity();
    const isEmailValid = this.validateEmail();
    const isPhoneValid = this.validatePhone();
    return isNameValid && isCityValid && isEmailValid && isPhoneValid;
  }

  // === MODAL PRINCIPAL ===
  openCreateModal() {
    this.isEditing.set(false);
    this.selectedCampus.set(null);
    this.errors.set({ name: '', city: '', email: '', phone: '' });
    this.formData.set({
      name: '',
      city: '',
      address: '',
      phone: '',
      email: '',
      is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(campus: CampusType) {
    this.isEditing.set(true);
    this.selectedCampus.set(campus);
    this.errors.set({ name: '', city: '', email: '', phone: '' });
    this.formData.set({
      name: campus.name,
      city: campus.city,
      address: campus.address || '',
      phone: campus.phone || '',
      email: campus.email || '',
      is_active: campus.is_active
    });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ name: '', city: '', email: '', phone: '' });
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    this.submitting.set(true);

    if (this.isEditing() && this.selectedCampus()) {
      this.campusService.update(this.selectedCampus()!.id, data).subscribe({
        next: () => {
          this.toastr.success('Campus modifié avec succès');
          this.closeModal();
          this.loadCampuses();
          this.submitting.set(false);
        },
        error: (err) => {
          this.toastr.error(err.error?.message || 'Erreur lors de la modification');
          this.submitting.set(false);
        }
      });
    } else {
      this.campusService.create(data).subscribe({
        next: () => {
          this.toastr.success('Campus créé avec succès');
          this.closeModal();
          this.loadCampuses();
          this.submitting.set(false);
        },
        error: (err) => {
          this.toastr.error(err.error?.message || 'Erreur lors de la création');
          this.submitting.set(false);
        }
      });
    }
  }

  // === MODAL DE CONFIRMATION SUPPRESSION ===
  openDeleteModal(campus: CampusType) {
    this.campusToDelete.set(campus);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.campusToDelete.set(null);
  }

  confirmDelete() {
    const campus = this.campusToDelete();
    if (!campus) return;

    this.submitting.set(true);
    this.campusService.delete(campus.id).subscribe({
      next: () => {
        this.toastr.success('Campus supprimé avec succès');
        this.closeDeleteModal();
        this.loadCampuses();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      }
    });
  }

  toggleStatus(campus: CampusType) {
    this.campusService.toggleStatus(campus.id).subscribe({
      next: () => {
        const status = !campus.is_active ? 'activé' : 'désactivé';
        this.toastr.success(`Campus ${status} avec succès`);
        this.loadCampuses();
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du changement de statut');
      }
    });
  }
}