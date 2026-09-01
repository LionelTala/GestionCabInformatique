import { Component, signal, computed , inject} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { Auth } from '../../core/services/auth';
import { ToastrService } from 'ngx-toastr';
import { LoadingService } from '../../core/services/loading';

interface NavItem {
  label: string;
  route: string;
  icon: string;
  roles?: string[]; // Rôles autorisés à voir cet élément
}

@Component({
  imports: [CommonModule, RouterModule],
  selector: 'app-layout',
  styleUrl: './layout.css',
  templateUrl: './layout.html',
})
export class Layout {
   private auth = inject(Auth);
  private router = inject(Router);
  private toastr = inject(ToastrService);
  loadingService = inject(LoadingService);
  sidebarOpen = signal(false);
  user = signal<any>(null);

  // Navigation dynamique avec restrictions de rôles
  navItems = signal<NavItem[]>([
    { label: 'Dashboard', route: '/dashboard', icon: 'dashboard' },
    { label: 'Campus', route: '/campus', icon: 'corporate_fare', roles: ['super_admin', 'admin_global'] },
    { label: 'Utilisateurs', route: '/users', icon: 'admin_panel_settings', roles: ['super_admin', 'admin_global', 'admin_campus'] },

    // Les autres seront ajoutées au fur et à mesure
  ]);

  // Filtrer les éléments selon le rôle de l'utilisateur
  visibleNavItems = computed(() => {
    const user = this.user();
    if (!user) return [];

    return this.navItems().filter(item => {
      if (!item.roles) return true;
      return item.roles.includes(user.role);
    });
  });

  constructor(
 
  ) {
    this.user.set(this.auth.getUser());
  }

  toggleSidebar() {
    this.sidebarOpen.update(v => !v);
  }

  logout() {
    this.toastr.success('Déconnexion réussie', 'À bientôt !');
    this.auth.logout();
  }

  isActive(route: string): boolean {
    return this.router.url === route;
  }
}