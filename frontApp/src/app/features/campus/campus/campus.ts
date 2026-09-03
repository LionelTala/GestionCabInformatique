import { Component, signal, OnInit, inject, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CampusService, Campus as CampusType } from '../../../core/services/campus';

type FormErrors = Partial<{ name: string; city: string; email: string; phone: string }>;

@Component({
  imports: [CommonModule, RouterModule, FormsModule],
  selector: 'app-campus',
  styleUrl: './campus.css',
  templateUrl: './campus.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CampusManagement implements OnInit {
  private campusService = inject(CampusService);
  private toastr = inject(ToastrService);

  campuses = this.campusService.getCampuses();
  loading = signal(false);
  submitting = signal(false);
  togglingId = signal<number | null>(null);

  showModal = signal(false);
  isEditing = signal(false);
  selectedCampus = signal<CampusType | null>(null);

  showDeleteModal = signal(false);
  campusToDelete = signal<CampusType | null>(null);

  errors = signal<FormErrors>({});

  formData = signal({
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    is_active: true
  });

  ngOnInit() {
    this.loadCampuses();
  }

  loadCampuses() {
  this.campusService.loadCampuses(); // ✅ Le service gère tout
}

  validate(): boolean {
    const data = this.formData();
    const e: FormErrors = {};

    if (!data.name.trim()) e.name = 'Le nom est obligatoire';
    else if (data.name.trim().length < 2) e.name = 'Minimum 2 caractères';

    if (!data.city.trim()) e.city = 'La ville est obligatoire';
    else if (data.city.trim().length < 2) e.city = 'Minimum 2 caractères';

    if (data.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
      e.email = 'Email invalide';
    }

    if (data.phone.trim() && !/^[0-9+\-\s]{8,20}$/.test(data.phone.trim())) {
      e.phone = 'Numéro invalide (8-20 caractères)';
    }

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  openCreateModal() {
    this.isEditing.set(false);
    this.selectedCampus.set(null);
    this.errors.set({});
    this.formData.set({ name: '', city: '', address: '', phone: '', email: '', is_active: true });
    this.showModal.set(true);
  }

  openEditModal(campus: CampusType) {
    this.isEditing.set(true);
    this.selectedCampus.set(campus);
    this.errors.set({});
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
    this.errors.set({});
  }

  onSubmit() {
    if (!this.validate()) return;

    this.submitting.set(true);
    const obs = this.isEditing()
      ? this.campusService.update(this.selectedCampus()!.id, this.formData())
      : this.campusService.create(this.formData());

    obs.subscribe({
      next: () => {
        this.toastr.success(this.isEditing() ? 'Campus modifié' : 'Campus créé');
        this.closeModal();
        this.loadCampuses();
        this.submitting.set(false);
      },
      error: (err: any) => {
        if (err.status === 422 && err.error?.errors) {
          const e: FormErrors = {};
          for (const [key, msg] of Object.entries(err.error.errors)) {
            if (key in e || ['name', 'city', 'email', 'phone'].includes(key)) {
              e[key as keyof FormErrors] = Array.isArray(msg) ? msg[0] : msg as string;
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
        this.toastr.success('Campus supprimé');
        this.closeDeleteModal();
        this.loadCampuses();
        this.submitting.set(false);
      },
      error: () => this.submitting.set(false)
    });
  }

  toggleStatus(campus: CampusType) {
    this.togglingId.set(campus.id);
    this.campusService.toggleStatus(campus.id).subscribe({
      next: () => this.loadCampuses(),
      error: () => this.togglingId.set(null),
      complete: () => this.togglingId.set(null)
    });
  }
}