import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Campus } from './campus';

describe('Campus', () => {
  let component: Campus;
  let fixture: ComponentFixture<Campus>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Campus],
    }).compileComponents();

    fixture = TestBed.createComponent(Campus);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
