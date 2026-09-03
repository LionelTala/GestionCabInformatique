import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { RegistrationService } from '../../../core/services/registration';
import { FormationService } from '../../../core/services/formation';
import { CampusService } from '../../../core/services/campus';
import { AcademicYearService } from '../../../core/services/academic-year';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule, RouterModule],
  selector: 'app-registrations',
  styleUrl: './registrations.css',
  templateUrl: './registrations.html',
})
export class Registrations implements OnInit {
  private registrationService = inject(RegistrationService);
  private formationService = inject(FormationService);
  private campusService = inject(CampusService);
  private academicYearService = inject(AcademicYearService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  // === DONNÉES ===
  registrations = this.registrationService.getRegistrations();
  meta = this.registrationService.getMeta();
  loading = this.registrationService.getLoading();

  formations = this.formationService.getFormations();
  campuses = this.campusService.getCampuses();
  academicYears = this.academicYearService.getYears();
  currentUser = this.auth.getUser();

  submitting = signal(false);
  downloading = signal(false);
  selectedFile = signal<File | null>(null);
  photoPreview = signal<string | null>(null);

  // === PAGINATION & FILTRES ===
  currentPage = signal(1);
  filters = signal({
    campus_id: null as number | null,
    formation_id: null as number | null,
    academic_year_id: null as number | null,
    status: '' as string, // unpaid, partial, paid
  });

  // === MODAL CRÉATION ===
  showModal = signal(false);
  formData = signal({
    first_name: '', last_name: '', email: '', phone: '', address: '', date_of_birth: '',
    parent_name: '', parent_phone: '', formation_id: null as number | null,
    campus_id: null as number | null, academic_year_id: null as number | null,
    initial_payment: null as number | null,
  });

  errors = signal({
    first_name: '', last_name: '', email: '', formation_id: '', academic_year_id: '', initial_payment: ''
  });

  selectedFormationId = signal<number | null>(null);
  selectedFormation = computed(() => this.formations().find(f => f.id === this.selectedFormationId()));

  // === MODAL DÉTAILS ===
  showDetailModal = signal(false);
  selectedRegistration = signal<any>(null);

  // === MODAL SUPPRESSION ===
  showDeleteModal = signal(false);
  registrationToDelete = signal<any>(null);

  ngOnInit() {
    this.formationService.loadFormations().subscribe();
    this.academicYearService.loadYears().subscribe();
    this.campusService.loadCampuses();

    const user = this.currentUser;
    if (user && ['admin_campus', 'secretary'].includes(user.role)) {
      this.formData.update(d => ({ ...d, campus_id: user.campus_id }));
    }
    this.loadRegistrations(1);
  }

  // === CHARGEMENT & FILTRES ===
  loadRegistrations(page: number = 1) {
    this.currentPage.set(page);
    const filters = this.filters();
    const params: any = {};
    if (filters.campus_id) params.campus_id = filters.campus_id;
    if (filters.formation_id) params.formation_id = filters.formation_id;
    if (filters.academic_year_id) params.academic_year_id = filters.academic_year_id;
    if (filters.status) params.status = filters.status;

    this.registrationService.refresh(page, params);
  }

  applyFilters() { this.loadRegistrations(1); }

  resetFilters() {
    this.filters.set({ campus_id: null, formation_id: null, academic_year_id: null, status: '' });
    this.loadRegistrations(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) this.loadRegistrations(page);
  }

  getPages(): number[] {
    const total = this.meta().last_page;
    const current = this.meta().current_page;
    const pages: number[] = [];
    if (total <= 7) {
      for (let i = 1; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      if (current > 3) pages.push(-1);
      for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
      if (current < total - 2) pages.push(-1);
      pages.push(total);
    }
    return pages;
  }

  // === MODAL CRÉATION : ACTIONS ===
  openCreateModal() {
    const user = this.currentUser;
    const defaultYear = this.academicYears().find(y => y.is_current);
    this.formData.set({
      first_name: '', last_name: '', email: '', phone: '', address: '', date_of_birth: '',
      parent_name: '', parent_phone: '', formation_id: null,
      campus_id: user && ['admin_campus', 'secretary'].includes(user.role) ? user.campus_id : null,
      academic_year_id: defaultYear?.id || null, initial_payment: null,
    });
    this.errors.set({ first_name: '', last_name: '', email: '', formation_id: '', academic_year_id: '', initial_payment: '' });
    this.selectedFormationId.set(null);
    this.selectedFile.set(null);
    this.photoPreview.set(null);
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ first_name: '', last_name: '', email: '', formation_id: '', academic_year_id: '', initial_payment: '' });
    this.selectedFile.set(null);
    this.photoPreview.set(null);
  }

  onFormationChange(formationId: number) { this.selectedFormationId.set(formationId); }

  onFileSelected(event: any) {
    const file = event.target.files[0];
    if (file) {
      this.selectedFile.set(file);
      const reader = new FileReader();
      reader.onload = () => this.photoPreview.set(reader.result as string);
      reader.readAsDataURL(file);
    }
  }

  // === VALIDATIONS CRÉATION ===
  validateFirstName(): boolean {
    const value = this.formData().first_name.trim();
    if (!value) { this.errors.update(e => ({ ...e, first_name: 'Le prénom est obligatoire' })); return false; }
    this.errors.update(e => ({ ...e, first_name: '' })); return true;
  }
  validateLastName(): boolean {
    const value = this.formData().last_name.trim();
    if (!value) { this.errors.update(e => ({ ...e, last_name: 'Le nom est obligatoire' })); return false; }
    this.errors.update(e => ({ ...e, last_name: '' })); return true;
  }
  validateEmail(): boolean {
    const value = this.formData().email.trim();
    if (value && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value)) {
      this.errors.update(e => ({ ...e, email: 'Email invalide' })); return false;
    }
    this.errors.update(e => ({ ...e, email: '' })); return true;
  }
  validateInitialPayment(): boolean {
    const value = this.formData().initial_payment;
    if (value !== null && value !== undefined && value < 0) {
      this.errors.update(e => ({ ...e, initial_payment: 'Le montant ne peut pas être négatif' })); return false;
    }
    this.errors.update(e => ({ ...e, initial_payment: '' })); return true;
  }
  validateFormation(): boolean {
    if (!this.formData().formation_id) { this.errors.update(e => ({ ...e, formation_id: 'La formation est obligatoire' })); return false; }
    this.errors.update(e => ({ ...e, formation_id: '' })); return true;
  }
  validateAcademicYear(): boolean {
    if (!this.formData().academic_year_id) { this.errors.update(e => ({ ...e, academic_year_id: "L'année scolaire est obligatoire" })); return false; }
    this.errors.update(e => ({ ...e, academic_year_id: '' })); return true;
  }

  validateForm(): boolean {
    return this.validateFirstName() && this.validateLastName() && this.validateEmail() &&
           this.validateFormation() && this.validateAcademicYear() && this.validateInitialPayment();
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    const formData = new FormData();
    Object.keys(data).forEach(key => {
      const value = data[key as keyof typeof data];
      if (value !== null && value !== undefined) formData.append(key, value.toString());
    });
    if (this.selectedFile()) formData.append('photo', this.selectedFile()!);

    this.submitting.set(true);
    this.registrationService.create(formData).subscribe({
      next: () => {
        this.toastr.success('Inscription réussie !');
        this.closeModal();
        this.loadRegistrations(this.currentPage());
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || "Erreur lors de l'inscription");
        this.submitting.set(false);
      }
    });
  }

  // === MODAL DÉTAILS & SUPPRESSION ===
  viewRegistration(registration: any) {
    this.selectedRegistration.set(registration);
    this.showDetailModal.set(true);
  }
  closeDetailModal() {
    this.showDetailModal.set(false);
    this.selectedRegistration.set(null);
  }

  openDeleteModal(registration: any) {
    this.registrationToDelete.set(registration);
    this.showDeleteModal.set(true);
  }
  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.registrationToDelete.set(null);
  }

  confirmDelete() {
    const registration = this.registrationToDelete();
    if (!registration) return;

    this.submitting.set(true);
    this.registrationService.delete(registration.id).subscribe({
      next: () => {
        this.toastr.success('Inscription supprimée avec succès');
        this.closeDeleteModal();
        this.loadRegistrations(this.currentPage());
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      }
    });
  }

  // === TÉLÉCHARGEMENT PDF ===
  downloadForm(registration: any) {
    this.downloading.set(true);
    this.registrationService.downloadForm(registration.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `fiche-inscription-${registration.student?.registration_number || registration.id}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
        this.downloading.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du téléchargement');
        this.downloading.set(false);
      }
    });
  }

  // === UTILITAIRES ===
  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      unpaid: 'Non payé',
      partial: 'Partiel',
      paid: 'Soldé',
      confirmed: 'Validé' // Fallback pour anciennes données
    };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = {
      unpaid: 'bg-red-100 text-red-700',
      partial: 'bg-yellow-100 text-yellow-700',
      paid: 'bg-green-100 text-green-700',
      confirmed: 'bg-green-100 text-green-700'
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
  }

  canManage(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global', 'admin_campus', 'secretary'].includes(user.role);
  }

  canSeeCampus(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  getCampusName(campusId: number): string {
    const campus = this.campuses().find(c => c.id === campusId);
    return campus ? campus.name : '-';
  }

  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
}