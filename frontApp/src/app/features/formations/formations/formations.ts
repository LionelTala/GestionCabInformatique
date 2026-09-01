import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { FormationService, Formation } from '../../../core/services/formation';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-formations',
  styleUrl: './formations.css',
  templateUrl: './formations.html',
})
export class Formations implements OnInit {
  private formationService = inject(FormationService);
  private toastr = inject(ToastrService);
  private auth = inject(Auth);

  formations = this.formationService.getFormations();
  currentUser = this.auth.getUser();
  submitting = signal(false);

  // Modal principal
  showModal = signal(false);
  isEditing = signal(false);
  selectedFormation = signal<Formation | null>(null);

  // Modal de confirmation
  showDeleteModal = signal(false);
  formationToDelete = signal<Formation | null>(null);

  // Formulaire
  formData = signal({
    name: '',
    abbreviation: '',
    tuition_fees: 0,
    duration_months: 0,
    is_active: true
  });

  // Erreurs
  errors = signal({
    name: '',
    abbreviation: '',
    tuition_fees: '',
    duration_months: '',
  });

  ngOnInit() {
    this.formationService.refresh();
  }

  // === VALIDATIONS ===
  validateName(): boolean {
    const value = this.formData().name.trim();
    if (!value) {
      this.errors.update(e => ({ ...e, name: 'Le nom de la formation est obligatoire' }));
      return false;
    }
    if (value.length < 3) {
      this.errors.update(e => ({ ...e, name: 'Le nom doit contenir au moins 3 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, name: '' }));
    return true;
  }

  validateAbbreviation(): boolean {
    const value = this.formData().abbreviation.trim().toUpperCase();
    if (!value) {
      this.errors.update(e => ({ ...e, abbreviation: 'L\'abréviation est obligatoire' }));
      return false;
    }
    if (value.length < 2 || value.length > 10) {
      this.errors.update(e => ({ ...e, abbreviation: 'L\'abréviation doit contenir entre 2 et 10 caractères' }));
      return false;
    }
    this.errors.update(e => ({ ...e, abbreviation: '' }));
    return true;
  }

  validateTuitionFees(): boolean {
    const value = this.formData().tuition_fees;
    if (value === null || value === undefined || value === 0) {
      this.errors.update(e => ({ ...e, tuition_fees: 'Les frais de scolarité sont obligatoires' }));
      return false;
    }
    if (value < 0) {
      this.errors.update(e => ({ ...e, tuition_fees: 'Les frais ne peuvent pas être négatifs' }));
      return false;
    }
    this.errors.update(e => ({ ...e, tuition_fees: '' }));
    return true;
  }

  validateDuration(): boolean {
    const value = this.formData().duration_months;
    if (value === null || value === undefined || value === 0) {
      this.errors.update(e => ({ ...e, duration_months: 'La durée est obligatoire' }));
      return false;
    }
    if (value < 1) {
      this.errors.update(e => ({ ...e, duration_months: 'La durée doit être d\'au moins 1 mois' }));
      return false;
    }
    this.errors.update(e => ({ ...e, duration_months: '' }));
    return true;
  }

  validateForm(): boolean {
    return this.validateName() && 
           this.validateAbbreviation() && 
           this.validateTuitionFees() && 
           this.validateDuration();
  }

  // === MODAL PRINCIPAL ===
  openCreateModal() {
    this.isEditing.set(false);
    this.selectedFormation.set(null);
    this.errors.set({ name: '', abbreviation: '', tuition_fees: '', duration_months: '' });
    this.formData.set({
      name: '',
      abbreviation: '',
      tuition_fees: 0,
      duration_months: 0,
      is_active: true
    });
    this.showModal.set(true);
  }

  openEditModal(formation: Formation) {
    this.isEditing.set(true);
    this.selectedFormation.set(formation);
    this.errors.set({ name: '', abbreviation: '', tuition_fees: '', duration_months: '' });
    this.formData.set({
      name: formation.name,
      abbreviation: formation.abbreviation,
      tuition_fees: formation.tuition_fees,
      duration_months: formation.duration_months,
      is_active: formation.is_active
    });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ name: '', abbreviation: '', tuition_fees: '', duration_months: '' });
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    this.submitting.set(true);

    if (this.isEditing() && this.selectedFormation()) {
      this.formationService.update(this.selectedFormation()!.id, data).subscribe({
        next: () => {
          this.toastr.success('Formation modifiée avec succès');
          this.closeModal();
          this.formationService.refresh();
          this.submitting.set(false);
        },
        error: (err) => {
          this.toastr.error(err.error?.message || 'Erreur lors de la modification');
          this.submitting.set(false);
        }
      });
    } else {
      this.formationService.create(data).subscribe({
        next: () => {
          this.toastr.success('Formation créée avec succès');
          this.closeModal();
          this.formationService.refresh();
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
  openDeleteModal(formation: Formation) {
    this.formationToDelete.set(formation);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.formationToDelete.set(null);
  }

  confirmDelete() {
    const formation = this.formationToDelete();
    if (!formation) return;

    this.submitting.set(true);
    this.formationService.delete(formation.id).subscribe({
      next: () => {
        this.toastr.success('Formation supprimée avec succès');
        this.closeDeleteModal();
        this.formationService.refresh();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      }
    });
  }

  toggleStatus(formation: Formation) {
    this.formationService.toggleStatus(formation.id).subscribe({
      next: () => {
        const status = !formation.is_active ? 'activée' : 'désactivée';
        this.toastr.success(`Formation ${status} avec succès`);
        this.formationService.refresh();
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

  formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}