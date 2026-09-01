import { Component, signal, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Auth } from '../../core/services/auth';
import { ToastrService } from 'ngx-toastr';
import { LoadingService } from '../../core/services/loading';
import { AcademicYearService, AcademicYear } from '../../core/services/academic-year';

interface NavItem {
  label: string;
  route: string;
  icon: string;
  roles?: string[];
}

@Component({
  imports: [CommonModule, RouterModule, FormsModule],
  selector: 'app-layout',
  styleUrl: './layout.css',
  templateUrl: './layout.html',
})
export class Layout implements OnInit {
  private auth = inject(Auth);
  private router = inject(Router);
  private toastr = inject(ToastrService);
  private academicYearService = inject(AcademicYearService);
  loadingService = inject(LoadingService);

  sidebarOpen = signal(false);
  user = signal<any>(null);
  years = signal<AcademicYear[]>([]);
  currentYearId = signal<number | null>(null);
  isSwitching = signal(false);

  navItems = signal<NavItem[]>([
    { label: 'Dashboard', route: '/dashboard', icon: 'dashboard' },
    { label: 'Campus', route: '/campus', icon: 'corporate_fare', roles: ['super_admin', 'admin_global'] },
    { label: 'Utilisateurs', route: '/users', icon: 'admin_panel_settings', roles: ['super_admin', 'admin_global', 'admin_campus'] },
    { label: 'Années scolaires', route: '/academic-years', icon: 'event_note', roles: ['super_admin', 'admin_global'] },
    { label: 'Formations', route: '/formations', icon: 'school', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
  ]);

  visibleNavItems = computed(() => {
    const user = this.user();
    if (!user) return [];

    return this.navItems().filter(item => {
      if (!item.roles) return true;
      return item.roles.includes(user.role);
    });
  });

  currentYearLabel = computed(() => {
    const year = this.years().find(y => y.id === this.currentYearId());
    return year?.label || 'Année';
  });

  ngOnInit() {
    this.user.set(this.auth.getUser());
    this.loadYears();
  }

  loadYears() {
    this.academicYearService.loadYears().subscribe({
      next: (response) => {
        this.years.set(response.data);
        this.currentYearId.set(response.current_year_id);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du chargement des années');
      }
    });
  }

  toggleSidebar() {
    this.sidebarOpen.update(v => !v);
  }

  switchYear(yearId: number) {
    const id = typeof yearId === 'string' ? parseInt(yearId, 10) : yearId;
    if (id === this.currentYearId()) return;

    this.isSwitching.set(true);
    this.academicYearService.switchYear(id).subscribe({
      next: () => {
        this.currentYearId.set(id);
        this.toastr.success('Année scolaire changée avec succès');
        this.isSwitching.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du changement d\'année');
        this.isSwitching.set(false);
      }
    });
  }

  logout() {
    this.toastr.success('Déconnexion réussie', 'À bientôt !');
    this.auth.logout();
  }

  isActive(route: string): boolean {
    return this.router.url === route;
  }

  canManageYears(): boolean {
    const user = this.user();
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }
}