import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { StudentService } from '../../../core/services/student.service';
import { FormationService } from '../../../core/services/formation';
import { CampusService } from '../../../core/services/campus';
import { AcademicYearService } from '../../../core/services/academic-year';
import { Auth } from '../../../core/services/auth';
import { environment } from '../../../../environments/environment';
import { ImageCompressionService, ACCEPTED_IMAGE_EXTENSIONS } from '../../../core/services/image-compression.service';


@Component({
  selector: 'app-students',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './students.html',
  styleUrl: './students.css'
})
export class StudentsComponent implements OnInit {
  baseUrl = environment.apiUrl;

  private studentService = inject(StudentService);
  private formationService = inject(FormationService);
  private campusService = inject(CampusService);
  private academicYearService = inject(AcademicYearService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);
  private imageCompression = inject(ImageCompressionService);


  students = this.studentService.getStudents();
  meta = this.studentService.getMeta();
  loading = this.studentService.getLoading();

  formations = this.formationService.getFormations();
  campuses = this.campusService.getCampuses();
  academicYears = this.academicYearService.getYears();
  currentUser = this.auth.getUser();

  currentPage = signal(1);
  submitting = signal(false);
  photoFile = signal<File | null>(null);
  photoPreview = signal<string>('');

  // ✅ Loader pour la compression d'image
  compressing = signal(false);

  // ✅ OPTIONS POUR LES NOUVEAUX CHAMPS
  languageOptions = [
    { value: 'francais', label: 'Français' },
    { value: 'anglais', label: 'Anglais' },
    { value: 'autre', label: 'Autre' },
  ];
  diplomaOptions = ['Aucun', 'BEPC / Brevet','Probatoire', 'Baccalauréat', 'BTS / DUT', 'Licence', 'Master', 'Doctorat', 'Autre'];

  // Filtres
  filters = signal({
    campus_id: null as number | null,
    academic_year_id: null as number | null,
    formation_id: null as number | null,
    search: '',
  });
  acceptedImageExtensions = ACCEPTED_IMAGE_EXTENSIONS;


  // Modal Détails
  showDetailModal = signal(false);
  selectedStudent = signal<any>(null);
  studentDetails = signal<any>(null);
  loadingDetails = signal(false);

  // Modal Édition
  showEditModal = signal(false);
  editFormData = signal<any>(null);
  editErrors = signal({
    first_name: '',
    last_name: '',
    email: '',
  });

  ngOnInit() {
    this.formationService.loadFormations().subscribe();
    this.academicYearService.loadYears().subscribe();
    this.campusService.loadCampuses();
    this.loadStudents(1);
  }

  loadStudents(page: number = 1) {
    this.currentPage.set(page);
    const filters = this.filters();
    const params: any = {};
    if (filters.campus_id) params.campus_id = filters.campus_id;
    if (filters.academic_year_id) params.academic_year_id = filters.academic_year_id;
    if (filters.formation_id) params.formation_id = filters.formation_id;
    if (filters.search) params.search = filters.search;

    this.studentService.loadStudents(page, params);
  }

  applyFilters() { this.loadStudents(1); }

  resetFilters() {
    this.filters.set({ campus_id: null, academic_year_id: null, formation_id: null, search: '' });
    this.loadStudents(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) {
      this.loadStudents(page);
    }
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

  // === MODAL DÉTAILS ===
  viewStudent(student: any) {
    this.selectedStudent.set(student);
    this.showDetailModal.set(true);
    this.loadingDetails.set(true);

    this.studentService.getStudent(student.id).subscribe({
      next: (res) => {
        this.studentDetails.set(res.data);
        this.loadingDetails.set(false);
      },
      error: () => {
        this.toastr.error('Erreur lors du chargement des détails');
        this.loadingDetails.set(false);
      }
    });
  }

  closeDetailModal() {
    this.showDetailModal.set(false);
    this.selectedStudent.set(null);
    this.studentDetails.set(null);
  }

  // === MODAL ÉDITION ===
  openEditModal() {
    const details = this.studentDetails();
    if (!details) return;

    this.editFormData.set({
      ...details.student,
      languages: this.parseLanguages(details.student.languages) // ✅ Réutilise la méthode
    });
    this.editErrors.set({ first_name: '', last_name: '', email: '' });

    // ✅ Aperçu de la photo existante ou avatar par défaut
    if (details.student.photo) {
      this.photoPreview.set(this.getPhotoUrl(details.student.id));
    } else {
      this.photoPreview.set('assets/default-avatar.png');
    }
    this.photoFile.set(null);

    // ✅ Reset l'input HTML (au cas où un fichier aurait été choisi avant)
    const input = document.getElementById('photoInput') as HTMLInputElement;
    if (input) input.value = '';

    this.showEditModal.set(true);
  }

  getPhotoUrl(studentId: number): string {
    return `${this.baseUrl}/students/${studentId}/photo`;
  }

  closeEditModal() {
    this.showEditModal.set(false);
    this.editFormData.set(null);
    this.editErrors.set({ first_name: '', last_name: '', email: '' });
    this.photoFile.set(null);
    this.photoPreview.set('');

    // ✅ Reset l'input HTML
    const input = document.getElementById('photoInput') as HTMLInputElement;
    if (input) input.value = '';
  }

  validateEditForm(): boolean {
    const data = this.editFormData();
    let valid = true;

    if (!data.first_name?.trim()) {
      this.editErrors.update(e => ({ ...e, first_name: 'Le prénom est obligatoire' }));
      valid = false;
    } else {
      this.editErrors.update(e => ({ ...e, first_name: '' }));
    }

    if (!data.last_name?.trim()) {
      this.editErrors.update(e => ({ ...e, last_name: 'Le nom est obligatoire' }));
      valid = false;
    } else {
      this.editErrors.update(e => ({ ...e, last_name: '' }));
    }

    if (data.email && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(data.email)) {
      this.editErrors.update(e => ({ ...e, email: 'Email invalide' }));
      valid = false;
    } else {
      this.editErrors.update(e => ({ ...e, email: '' }));
    }

    return valid;
  }

  // ✅ Sélection et compression de la photo
  async onPhotoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    // ✅ Validation via le service (accepte webp, heic, etc.)
    if (!this.imageCompression.isAcceptedImage(file)) {
      const ext = file.name.split('.').pop()?.toUpperCase() || 'inconnu';
      this.toastr.error(`Format "${ext}" non supporté. Utilisez JPG, PNG, WebP, HEIC...`);
      input.value = '';
      return;
    }

    // ✅ Limite absolue de sécurité (10 Mo)
    if (file.size > 10 * 1024 * 1024) {
      this.toastr.error('Fichier trop volumineux (max 10 Mo)');
      input.value = '';
      return;
    }

    this.compressing.set(true);

    try {
      const compressedFile = await this.imageCompression.compress(file, {
        maxWidth: 800,
        maxHeight: 800,
        quality: 0.85,
      });

      console.log(`📸 Compression: ${(file.size / 1024).toFixed(0)} Ko → ${(compressedFile.size / 1024).toFixed(0)} Ko`);

      this.photoFile.set(compressedFile);

      const reader = new FileReader();
      reader.onload = () => this.photoPreview.set(reader.result as string);
      reader.readAsDataURL(compressedFile);

    } catch (error) {
      console.error('Erreur compression:', error);
      this.toastr.error('Erreur lors du traitement de l\'image');
    } finally {
      this.compressing.set(false);
      input.value = ''; // ✅ permet de re-sélectionner le même fichier
    }
  }

  // ✅ Toggle d'une langue (checkbox)
  toggleEditLanguage(lang: string) {
    this.editFormData.update((d: any) => {
      if (!d) return d;
      const langs = [...(d.languages || [])];
      const idx = langs.indexOf(lang);
      if (idx > -1) {
        langs.splice(idx, 1);
      } else {
        langs.push(lang);
      }
      return { ...d, languages: langs };
    });
  }

  onSubmitEdit() {
    if (!this.validateEditForm()) {
      this.toastr.warning('Veuillez corriger les erreurs');
      return;
    }

    const data = this.editFormData();
    this.submitting.set(true);

    const formData = new FormData();
    formData.append('_method', 'PUT'); // ✅ Spoofing pour Laravel

    // 1. Champs standards (texte/nombre)
    const standardFields = [
      'first_name', 'last_name', 'email', 'phone', 'residence', 'date_of_birth','place_of_birth',
      'highest_diploma', 'diploma_year', 'parent_name', 'parent_phone'
    ];

    standardFields.forEach(key => {
      const value = data[key];
      // On envoie seulement si la valeur n'est pas null/undefined/chaîne vide
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value.toString());
      }
    });

    // ✅ 2. Langues (Tableau) : envoyer languages[] pour chaque élément
    if (data.languages && Array.isArray(data.languages)) {
      data.languages.forEach((lang: string) => {
        formData.append('languages[]', lang);
      });
    }

    // ✅ 3. Photo
    if (this.photoFile()) {
      formData.append('photo', this.photoFile()!);
    }

    this.studentService.updateStudent(data.id, formData).subscribe({
      next: () => {
        this.toastr.success('Informations modifiées avec succès');
        this.closeEditModal();
        this.closeDetailModal();
        this.loadStudents(this.currentPage());
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la modification');
        this.submitting.set(false);
      }
    });
  }

  // === UTILITAIRES ===
  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  getPaymentStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      unpaid: 'Non payé',
      partial: 'Partiel',
      paid: 'Soldé',
    };
    return labels[status] || status;
  }

  getPaymentStatusClass(status: string): string {
    const classes: Record<string, string> = {
      unpaid: 'bg-red-100 text-red-700',
      partial: 'bg-yellow-100 text-yellow-700',
      paid: 'bg-green-100 text-green-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
  }

  openPhotoSelector() {
    const input = document.getElementById('photoInput') as HTMLInputElement;
    if (input) {
      input.click();
    }
  }

  canSeeAllCampuses(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  canEdit(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global', 'admin_campus', 'secretary'].includes(user.role);
  }

  // === MODAL RAPPORT SCOLARITÉ ===
  showReportModal = signal(false);
  reportData = signal<any>(null);
  loadingReport = signal(false);
  reportFilters = signal({
    campus_id: null as number | null,
    formation_id: null as number | null,
    payment_status: '' as string,
  });

  openReportModal() {
    this.reportFilters.set({ campus_id: null, formation_id: null, payment_status: '' });
    this.reportData.set(null);
    this.showReportModal.set(true);
    this.loadReport();
  }

  closeReportModal() {
    this.showReportModal.set(false);
    this.reportData.set(null);
  }

  loadReport() {
    this.loadingReport.set(true);
    const f = this.reportFilters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.formation_id != null) params.formation_id = f.formation_id;
    if (f.payment_status) params.payment_status = f.payment_status;

    this.studentService.getScholarshipReport(params).subscribe({
      next: (res) => {
        this.reportData.set(res.data);
        this.loadingReport.set(false);
      },
      error: () => this.loadingReport.set(false)
    });
  }

  downloadReport() {
    const f = this.reportFilters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.formation_id != null) params.formation_id = f.formation_id;
    if (f.payment_status) params.payment_status = f.payment_status;
    this.studentService.downloadScholarshipReport(params);
  }

  // === MODAL LISTE SIMPLE ===
  showListModal = signal(false);
  listData = signal<any>(null);
  loadingList = signal(false);
  listFilters = signal({
    campus_id: null as number | null,
    formation_id: null as number | null,
    search: '',
  });

  openListModal() {
    this.listFilters.set({ campus_id: null, formation_id: null, search: '' });
    this.listData.set(null);
    this.showListModal.set(true);
    this.loadSimpleList();
  }

  closeListModal() {
    this.showListModal.set(false);
    this.listData.set(null);
  }

  loadSimpleList() {
    this.loadingList.set(true);
    const f = this.listFilters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.formation_id != null) params.formation_id = f.formation_id;
    if (f.search) params.search = f.search;

    this.studentService.getSimpleList(params).subscribe({
      next: (res) => {
        this.listData.set(res.data);
        this.loadingList.set(false);
      },
      error: () => this.loadingList.set(false)
    });
  }

  downloadList() {
    const f = this.listFilters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.formation_id != null) params.formation_id = f.formation_id;
    if (f.search) params.search = f.search;
    this.studentService.downloadSimpleList(params);
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = { paid: 'Soldé', partial: 'Partiel', unpaid: 'Non payé' };
    return labels[status] || status;
  }

  getStatusClass(status: string): string {
    const classes: Record<string, string> = { paid: 'bg-green-100 text-green-700', partial: 'bg-yellow-100 text-yellow-700', unpaid: 'bg-red-100 text-red-700' };
    return classes[status] || 'bg-gray-100 text-gray-700';
  }

  // ✅ Parse languages (string JSON, tableau, ou null) → toujours un tableau
  parseLanguages(languages: any): string[] {
    if (!languages) return [];
    if (Array.isArray(languages)) return languages;
    if (typeof languages === 'string') {
      try {
        const parsed = JSON.parse(languages);
        return Array.isArray(parsed) ? parsed : [];
      } catch {
        return [];
      }
    }
    return [];
  }
}