import { Component, signal, OnInit, inject, ChangeDetectionStrategy } from '@angular/core';
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
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AcademicYears implements OnInit {
  private service = inject(AcademicYearService);
  private toastr = inject(ToastrService);
  private auth = inject(Auth);

  years = this.service.getYears();
  currentUser = this.auth.getUser();
  loading = signal(false);
  submitting = signal(false);
  togglingId = signal<number | null>(null);

  showModal = signal(false);
  isEditing = signal(false);
  selectedYear = signal<AcademicYear | null>(null);
  
  // 🟢 CORRECTION 1 : Déclaration de la variable manquante pour la suppression
  yearToDelete = signal<AcademicYear | null>(null);
  showDeleteModal = signal(false);

  errors = signal<Partial<{ label: string; start_date: string; end_date: string }>>({});

  formData = signal({
    label: '',
    start_date: '',
    end_date: '',
    is_current: false,
    is_active: true
  });

  ngOnInit(): void {
    this.loadYears();
  }

  loadYears(): void {
    this.loading.set(true);
    this.service.loadYears().subscribe({
      complete: () => this.loading.set(false),
      error: () => this.loading.set(false)
    });
  }

  validate(): boolean {
    const d = this.formData();
    const e: Partial<{ label: string; start_date: string; end_date: string }> = {};

    if (!d.label.trim()) {
      e.label = 'Le libellé est obligatoire';
    } else if (!/^\d{4}-\d{4}$/.test(d.label.trim())) {
      e.label = 'Format invalide (2024-2025)';
    }

    if (!d.start_date) {
      e.start_date = 'Date de début obligatoire';
    }

    if (!d.end_date) {
      e.end_date = 'Date de fin obligatoire';
    } else if (d.start_date && d.end_date <= d.start_date) {
      e.end_date = 'Doit être après le début';
    }

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  openCreateModal(): void {
    this.isEditing.set(false);
    this.selectedYear.set(null);
    this.errors.set({});
    this.formData.set({
      label: '', start_date: '', end_date: '',
      is_current: false, is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(y: AcademicYear): void {
    this.isEditing.set(true);
    this.selectedYear.set(y);
    this.errors.set({});
    this.formData.set({
      label: y.label, start_date: y.start_date,
      end_date: y.end_date, is_current: y.is_current, is_active: y.is_active
    });
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
    this.errors.set({});
  }

  onSubmit(): void {
    if (!this.validate()) return;

    this.submitting.set(true);
    const obs = this.isEditing()
      ? this.service.update(this.selectedYear()!.id, this.formData())
      : this.service.create(this.formData());

    obs.subscribe({
      next: () => {
        this.toastr.success(this.isEditing() ? 'Année modifiée' : 'Année créée');
        this.closeModal();
        this.loadYears();
        this.submitting.set(false);
      },
      error: (err: any) => {
        if (err.status === 422 && err.error?.errors) {
          const e: Partial<{ label: string; start_date: string; end_date: string }> = {};
          for (const [key, msg] of Object.entries(err.error.errors)) {
            if (key === 'label' || key === 'start_date' || key === 'end_date') {
              e[key] = Array.isArray(msg) ? msg[0] : msg as string;
            }
          }
          this.errors.set(e);
        } else {
          this.toastr.error(err.error?.message || 'Erreur');
        }
        this.submitting.set(false);
      }
    });
  }

  openDeleteModal(y: AcademicYear): void {
    this.yearToDelete.set(y);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal(): void {
    this.showDeleteModal.set(false);
    this.yearToDelete.set(null);
  }

  confirmDelete(): void {
    const y = this.yearToDelete();
    if (!y) return;

    this.submitting.set(true);
    this.service.delete(y.id).subscribe({
      next: () => {
        this.toastr.success('Année supprimée');
        this.closeDeleteModal();
        this.loadYears();
        this.submitting.set(false);
      },
      error: () => this.submitting.set(false)
    });
  }

  toggleStatus(y: AcademicYear): void {
    this.togglingId.set(y.id);
    this.service.update(y.id, { is_active: !y.is_active }).subscribe({
      next: () => this.loadYears(),
      error: () => this.togglingId.set(null),
      // 🟢 CORRECTION 2 : Correction de la faute de frappe "toggingId" -> "togglingId"
      complete: () => this.togglingId.set(null)
    });
  }

  canManage(): boolean {
    return !!this.currentUser &&
      ['super_admin', 'admin_global'].includes(this.currentUser.role);
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    });
  }
}