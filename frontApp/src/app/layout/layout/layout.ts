// layout.ts
import {
  Component,
  signal,
  computed,
  inject,
  OnInit,
  OnDestroy,
  ChangeDetectionStrategy,      // ⚡
  ChangeDetectorRef,             // ⚡
  ElementRef,                    // ⚡ click outside
  HostListener,                  // ⚡ click outside
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Auth } from '../../core/services/auth';
import { LoadingService } from '../../core/services/loading';
import { AcademicYearService, AcademicYear } from '../../core/services/academic-year';
import { PusherService } from '../../core/services/pusher';
import { NotificationService } from '../../core/services/notification';
import { filter } from 'rxjs/operators';
import { CampusService } from '../../core/services/campus'; // Adapte le chemin


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
  changeDetection: ChangeDetectionStrategy.OnPush, // ⚡ #1 cause de latence
})
export class Layout implements OnInit, OnDestroy {
  private auth = inject(Auth);
  private router = inject(Router);
  private toastr = inject(ToastrService);
  private academicYearService = inject(AcademicYearService);
  private pusher = inject(PusherService);
  private notificationService = inject(NotificationService);
  private cdr = inject(ChangeDetectorRef);      // ⚡
  private el = inject(ElementRef);   
    private campusService = inject(CampusService);
           // ⚡ click outside
  loadingService = inject(LoadingService);

  sidebarOpen = signal(false);
  user = signal<any>(null);
  years = signal<AcademicYear[]>([]);
  currentYearId = signal<number | null>(null);
  isSwitching = signal(false);
  showNotifications = signal(false);

  // ⚡ Notifications — PAS chargées au démarrage
  notifications = this.notificationService.getNotifications();
  unreadCount = this.notificationService.getUnreadCount();

    navItems: NavItem[] = [
    { label: 'Tableau de bord', route: '/dashboard', icon: 'grid_view' },
    { label: 'Campus', route: '/campus', icon: 'apartment', roles: ['super_admin', 'admin_global'] },
    { label: 'Utilisateurs', route: '/users', icon: 'group', roles: ['super_admin', 'admin_global', 'admin_campus'] },
    { label: 'Formations', route: '/formations', icon: 'school', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
    { label: 'Inscriptions', route: '/registrations', icon: 'person_add', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
    { label: 'Paiements', route: '/payments', icon: 'payments', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
      { label: 'Étudiants', route: '/students', icon: 'school', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
      { label: 'Dépenses', route: '/expenses', icon: 'receipt_long', roles: ['super_admin', 'admin_global', 'admin_campus', 'secretary'] },
   { label: 'Mouvements', route: '/financial-movements', icon: 'account_balance', roles: ['super_admin', 'admin_global', 'admin_campus'] },
    { label: 'Journal', route: '/logs', icon: 'history', roles: ['super_admin', 'admin_global', 'admin_campus'] },
    { label: 'Années scolaires', route: '/academic-years', icon: 'calendar_month', roles: ['super_admin', 'admin_global'] },
  ];

  visibleNavItems = computed(() => {
    const u = this.user();
    if (!u) return [];
    return this.navItems.filter(item => !item.roles || item.roles.includes(u.role));
  });

  currentYearLabel = computed(() => {
    const year = this.years().find(y => y.id === this.currentYearId());
    return year?.label || 'Année';
  });

  // ⚡ Route courante pour active state (évite les appels répétés)
  currentRoute = signal('');

  private roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    admin_global: 'Admin Global',
    admin_campus: 'Admin Campus',
    secretaire: 'Secrétaire',
  };

 ngOnInit() {
  const u = this.auth.getUser();
      this.campusService.loadCampuses();


  if (u) {
    this.user.set(u);
    this.setupAfterUser(u);
  } else {
    this.auth.me().subscribe({
      next: (freshUser: any) => {
        this.user.set(freshUser);
        this.setupAfterUser(freshUser);
      },
      error: () => {} // errorInterceptor gère la redirection
    });
  }

  this.loadYears();

  this.router.events.pipe(
    filter(e => e instanceof NavigationEnd)
  ).subscribe((e: any) => {
    this.currentRoute.set(e.urlAfterRedirects || e.url);
  });
}

private setupAfterUser(u: any) {
  if (u && u.role !== 'secretary') {
    this.listenPusherNotifications();
  }
}

  ngOnDestroy() {}

  // === ANNÉES SCOLAIRES ===
  loadYears() {
    this.academicYearService.loadYears().subscribe({
      next: (response) => {
        this.years.set(response.data);
        this.currentYearId.set(response.current_year_id);
      },
      error: () => {
        // ⚡ Pas de toastr ici — silencieux au chargement
      }
    });
  }

  switchYear(yearId: number) {
    const id = typeof yearId === 'string' ? parseInt(yearId, 10) : yearId;
    if (id === this.currentYearId()) return;

    this.isSwitching.set(true);
    this.academicYearService.switchYear(id).subscribe({
      next: () => {
        this.currentYearId.set(id);
        this.isSwitching.set(false);
      },
      error: () => {
        this.isSwitching.set(false);
      }
    });
  }

  // === NOTIFICATIONS PUSHER ===
  listenPusherNotifications() {
    this.pusher.listenNewRegistration((data) => {
      this.notificationService.addNotification({
        id: Date.now(),
        user_id: this.user()?.id || 0,
        type: data.type || 'new_registration',
        title: data.title || 'Nouvelle inscription',
        message: data.message,
        link: data.link || null,
        is_read: false,
        created_at: data.created_at || new Date().toISOString()
      });

      this.toastr.info(data.message, data.title, {
        timeOut: 4000,
        closeButton: true,
        positionClass: 'toast-top-right',
      });
    });
  }

  // ⚡ Notifications chargées SEULEMENT au clic sur la cloche
  toggleNotifications() {
    const willOpen = !this.showNotifications();
    this.showNotifications.set(willOpen);
    if (willOpen) {
      this.notificationService.loadNotifications();
    }
  }

  // ⚡ Fermer dropdown au clic dehors
  @HostListener('document:click', ['$event.target'])
onClickOutside(target: EventTarget | null) {
  if (this.showNotifications() && target instanceof HTMLElement && !this.el.nativeElement.querySelector('.notif-zone')?.contains(target)) {
    this.showNotifications.set(false);
  }
}

markAllAsRead() {
  this.notificationService.markAllAsRead().subscribe({ error: () => {} });
}

markAsRead(id: number) {
  this.notificationService.markAsRead(id).subscribe({ error: () => {} });
}

  // === SIDEBAR ===
  toggleSidebar() {
    this.sidebarOpen.update(v => !v);
  }

  // ⚡ Fermer sidebar mobile au clic sur un lien
  closeSidebarOnMobile() {
    if (this.sidebarOpen()) {
      this.sidebarOpen.set(false);
    }
  }

  // === AUTH ===
  logout() {
    this.auth.logout().subscribe();
  }

  // === HELPERS ===
  getRoleLabel(role: string): string {
    return this.roleLabels[role] || role;
  }
  canManageYears(): boolean {
  const user = this.user();
  return !!user && ['super_admin', 'admin_global'].includes(user.role);
}

  getInitials(): string {
    const u = this.user();
    if (!u) return '';
    return `${u.prenom?.[0] || ''}${u.nom?.[0] || ''}`.toUpperCase();
  }

  formatDate(date: string): string {
    if (!date) return '';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }
}