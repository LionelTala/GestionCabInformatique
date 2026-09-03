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

  // Filtres
  filters = signal({
    campus_id: null as number | null,
    academic_year_id: null as number | null,
    formation_id: null as number | null,
    search: '',
  });

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

    this.editFormData.set({ ...details.student });
    this.editErrors.set({ first_name: '', last_name: '', email: '' });
    
    // ✅ Utiliser la route sécurisée pour la prévisualisation
    if (details.student.photo) {
      this.photoPreview.set(this.getPhotoUrl(details.student.id));
    } else {
      this.photoPreview.set('assets/default-avatar.png');
    }
    this.photoFile.set(null);
    
    this.showEditModal.set(true);
  }
   getPhotoUrl(studentId: number): string {
    return `${this.baseUrl}/students/${studentId}/photo`;
  }

  closeEditModal() {
    this.showEditModal.set(false);
    this.editFormData.set(null);
    this.editErrors.set({ first_name: '', last_name: '', email: '' });
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
  onPhotoSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
      this.photoFile.set(file);
      const reader = new FileReader();
      reader.onload = () => this.photoPreview.set(reader.result as string);
      reader.readAsDataURL(file);
    }
  }

    // === COPIE EXACTE DE LA LOGIQUE DE REGISTRATIONS ===
  
 

  onSubmitEdit() {
    if (!this.validateEditForm()) {
      this.toastr.warning('Veuillez corriger les erreurs');
      return;
    }

    const data = this.editFormData();
    this.submitting.set(true);

    // ✅ CONSTRUCTION DU FORMDATA EXACTEMENT COMME DANS REGISTRATIONS
    const formData = new FormData();
    formData.append('_method', 'PUT');
    
    Object.keys(data).forEach(key => {
      const value = data[key as keyof typeof data];
      // On ajoute la valeur si elle n'est ni null ni undefined
      if (value !== null && value !== undefined) {
        formData.append(key, value.toString());
      }
    });

    // ✅ AJOUT DE LA PHOTO À LA FIN
    if (this.photoFile()) {
      formData.append('photo', this.photoFile()!);
    }

    // ✅ APPEL AU SERVICE
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
    // ✅ MÉTHODE POUR OUVRIR LE SÉLECTEUR DE FICHIER
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
}