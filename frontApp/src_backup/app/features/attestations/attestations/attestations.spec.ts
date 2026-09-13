import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Attestations } from './attestations';

describe('Attestations', () => {
  let component: Attestations;
  let fixture: ComponentFixture<Attestations>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Attestations],
    }).compileComponents();

    fixture = TestBed.createComponent(Attestations);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
