import { Component, signal, OnInit, inject, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { FormationService, Formation } from '../../../core/services/formation';
import { Auth } from '../../../core/services/auth';

type FormErrors = Partial<{ name: string; abbreviation: string; tuition_fees: string; duration_months: string }>;

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-formations',
  styleUrl: './formations.css',
  templateUrl: './formations.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Formations implements OnInit {
  private formationService = inject(FormationService);
  private toastr = inject(ToastrService);
  private auth = inject(Auth);

  formations = this.formationService.getFormations();
  currentUser = this.auth.getUser();
  loading = signal(false);
  submitting = signal(false);
  togglingId = signal<number | null>(null);

  showModal = signal(false);
  isEditing = signal(false);
  selectedFormation = signal<Formation | null>(null);
  showDeleteModal = signal(false);
  formationToDelete = signal<Formation | null>(null);

  errors = signal<FormErrors>({});

  formData = signal({
    name: '', abbreviation: '', tuition_fees: 0, duration_months: 0, is_active: true
  });

  ngOnInit() {
    this.loadFormations();
  }

  loadFormations() {
    this.loading.set(true);
    this.formationService.loadFormations().subscribe({
      complete: () => this.loading.set(false),
      error: () => this.loading.set(false)
    });
  }

  validate(): boolean {
    const d = this.formData();
    const e: FormErrors = {};

    if (!d.name.trim()) e.name = 'Le nom est obligatoire';
    else if (d.name.trim().length < 3) e.name = 'Minimum 3 caractères';

    if (!d.abbreviation.trim()) e.abbreviation = "L'abréviation est obligatoire";
    else if (d.abbreviation.trim().length < 2 || d.abbreviation.trim().length > 10) e.abbreviation = 'Entre 2 et 10 caractères';

    if (!d.tuition_fees || d.tuition_fees <= 0) e.tuition_fees = 'Les frais sont obligatoires';

    if (!d.duration_months || d.duration_months < 1) e.duration_months = 'Minimum 1 mois';

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  openCreateModal() {
    this.isEditing.set(false);
    this.selectedFormation.set(null);
    this.errors.set({});
    this.formData.set({ name: '', abbreviation: '', tuition_fees: 0, duration_months: 0, is_active: true });
    this.showModal.set(true);
  }

  openEditModal(f: Formation) {
    this.isEditing.set(true);
    this.selectedFormation.set(f);
    this.errors.set({});
    this.formData.set({ name: f.name, abbreviation: f.abbreviation, tuition_fees: f.tuition_fees, duration_months: f.duration_months, is_active: f.is_active });
    this.showModal.set(true);
  }

  closeModal() { this.showModal.set(false); this.errors.set({}); }

  onSubmit() {
    if (!this.validate()) return;
    this.submitting.set(true);

    const obs = this.isEditing()
      ? this.formationService.update(this.selectedFormation()!.id, this.formData())
      : this.formationService.create(this.formData());

    obs.subscribe({
      next: () => { this.toastr.success(this.isEditing() ? 'Formation modifiée' : 'Formation créée'); this.closeModal(); this.loadFormations(); this.submitting.set(false); },
      error: (err: any) => {
        if (err.status === 422 && err.error?.errors) {
          const e: FormErrors = {};
          for (const [key, msg] of Object.entries(err.error.errors)) {
            if (['name', 'abbreviation', 'tuition_fees', 'duration_months'].includes(key)) {
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

  openDeleteModal(f: Formation) { this.formationToDelete.set(f); this.showDeleteModal.set(true); }
  closeDeleteModal() { this.showDeleteModal.set(false); this.formationToDelete.set(null); }

  confirmDelete() {
    const f = this.formationToDelete();
    if (!f) return;
    this.submitting.set(true);
    this.formationService.delete(f.id).subscribe({
      next: () => { this.toastr.success('Formation supprimée'); this.closeDeleteModal(); this.loadFormations(); this.submitting.set(false); },
      error: () => this.submitting.set(false)
    });
  }

  toggleStatus(f: Formation) {
    this.togglingId.set(f.id);
    this.formationService.toggleStatus(f.id).subscribe({
      next: () => this.loadFormations(),
      error: () => this.togglingId.set(null),
      complete: () => this.togglingId.set(null)
    });
  }

  canManage(): boolean {
    return !!this.currentUser && ['super_admin', 'admin_global'].includes(this.currentUser.role);
  }

  formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}