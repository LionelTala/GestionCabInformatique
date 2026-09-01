import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AcademicYearService, AcademicYear } from '../../../core/services/academic-year';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-academic-years',
  styleUrl: './academic-years.css',
  templateUrl: './academic-years.html',
})
export class AcademicYears implements OnInit {
  private academicYearService = inject(AcademicYearService);
  private toastr = inject(ToastrService);
  private auth = inject(Auth);

  years = this.academicYearService.getYears();
  currentUser = this.auth.getUser();
  loading = signal(true);
  submitting = signal(false);

  showModal = signal(false);
  isEditing = signal(false);
  selectedYear = signal<AcademicYear | null>(null);

  showDeleteModal = signal(false);
  yearToDelete = signal<AcademicYear | null>(null);

  formData = signal({
    label: '',
    start_date: '',
    end_date: '',
    is_current: false,
    is_active: true
  });

  errors = signal({
    label: '',
    start_date: '',
    end_date: '',
  });

  ngOnInit() {
    this.loading.set(true);
    this.academicYearService.refresh();
    this.loading.set(false);
  }

  // === VALIDATIONS ===
  validateLabel(): boolean {
    const value = this.formData().label.trim();
    if (!value) {
      this.errors.update(e => ({ ...e, label: 'Le libellé est obligatoire' }));
      return false;
    }
    if (!/^\d{4}-\d{4}$/.test(value)) {
      this.errors.update(e => ({ ...e, label: 'Format invalide. Exemple: 2024-2025' }));
      return false;
    }
    this.errors.update(e => ({ ...e, label: '' }));
    return true;
  }

  validateStartDate(): boolean {
    if (!this.formData().start_date) {
      this.errors.update(e => ({ ...e, start_date: 'La date de début est obligatoire' }));
      return false;
    }
    this.errors.update(e => ({ ...e, start_date: '' }));
    return true;
  }

  validateEndDate(): boolean {
    if (!this.formData().end_date) {
      this.errors.update(e => ({ ...e, end_date: 'La date de fin est obligatoire' }));
      return false;
    }
    if (this.formData().start_date && this.formData().end_date <= this.formData().start_date) {
      this.errors.update(e => ({ ...e, end_date: 'La date de fin doit être postérieure à la date de début' }));
      return false;
    }
    this.errors.update(e => ({ ...e, end_date: '' }));
    return true;
  }

  validateForm(): boolean {
    return this.validateLabel() && this.validateStartDate() && this.validateEndDate();
  }

  // === MODAL PRINCIPAL ===
  openCreateModal() {
    this.isEditing.set(false);
    this.selectedYear.set(null);
    this.errors.set({ label: '', start_date: '', end_date: '' });
    this.formData.set({
      label: '',
      start_date: '',
      end_date: '',
      is_current: false,
      is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(year: AcademicYear) {
    this.isEditing.set(true);
    this.selectedYear.set(year);
    this.errors.set({ label: '', start_date: '', end_date: '' });
    this.formData.set({
      label: year.label,
      start_date: year.start_date,
      end_date: year.end_date,
      is_current: year.is_current,
      is_active: year.is_active
    });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ label: '', start_date: '', end_date: '' });
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    this.submitting.set(true);

    if (this.isEditing() && this.selectedYear()) {
      this.academicYearService.update(this.selectedYear()!.id, data).subscribe({
        next: () => {
          this.toastr.success('Année scolaire modifiée avec succès');
          this.closeModal();
          this.academicYearService.refresh();
          this.submitting.set(false);
        },
        error: (err) => {
          this.toastr.error(err.error?.message || 'Erreur lors de la modification');
          this.submitting.set(false);
        }
      });
    } else {
      this.academicYearService.create(data).subscribe({
        next: () => {
          this.toastr.success('Année scolaire créée avec succès');
          this.closeModal();
          this.academicYearService.refresh();
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
  openDeleteModal(year: AcademicYear) {
    this.yearToDelete.set(year);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.yearToDelete.set(null);
  }

  confirmDelete() {
    const year = this.yearToDelete();
    if (!year) return;

    this.submitting.set(true);
    this.academicYearService.delete(year.id).subscribe({
      next: () => {
        this.toastr.success('Année scolaire supprimée avec succès');
        this.closeDeleteModal();
        this.academicYearService.refresh();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      }
    });
  }

  toggleStatus(year: AcademicYear) {
    this.academicYearService.update(year.id, { is_active: !year.is_active }).subscribe({
      next: () => {
        const status = !year.is_active ? 'activée' : 'désactivée';
        this.toastr.success(`Année ${status} avec succès`);
        this.academicYearService.refresh();
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du changement de statut');
      }
    });
  }

  canManage(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  }
}